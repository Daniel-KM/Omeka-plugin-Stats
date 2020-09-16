<?php
/**
 * Controller to browse Stats.
 * @package Stats
 */
class Stats_BrowseController extends Omeka_Controller_AbstractActionController
{
    private $_userStatus;

    /**
     * Controller-wide initialization. Sets the underlying model to use.
     */
    public function init()
    {
        // The default table depends on action.
        $action = $this->getRequest()->getParam('action', 'index');
        switch ($action) {
            case 'by-field':
                $this->_helper->db->setDefaultModelName('Hit');
                break;
            default:
                $this->_helper->db->setDefaultModelName('Stat');
                break;
        }
        $this->_userStatus = is_admin_theme()
            ? get_option('stats_default_user_status_admin')
            : get_option('stats_default_user_status_public');
    }

    /**
     * Forward to the 'browse by page' action
     *
     * @see self::browseAction()
     */
    public function indexAction()
    {
        $this->forward('by-page');
    }

    /**
     * Forward to the 'by-page' action
     *
     * @see self::browseAction()
     */
    public function browseAction()
    {
        $this->forward('by-page');
    }

    /**
     * Browse rows by page action.
     */
    public function byPageAction()
    {
        $this->setParam('type', 'page');
        $this->_browseAction();
        $this->view->assign(array(
            'stats_type' => 'page',
            'user_status' => $this->_userStatus,
        ));
        $this->_helper->viewRenderer('by-stat');
    }

    /**
     * Browse rows by record action.
     */
    public function byRecordAction()
    {
        $this->setParam('type', 'record');
        $this->_browseAction();
        $this->view->assign(array(
            'stats_type' => 'record',
            'user_status' => $this->_userStatus,
        ));
        $this->_helper->viewRenderer('by-stat');
    }

    /**
     * Browse rows by download action.
     */
    public function byDownloadAction()
    {
        $this->setParam('type', 'download');
        $this->_browseAction();
        $this->view->assign(array(
            'stats_type' => 'download',
            'user_status' => $this->_userStatus,
        ));
        $this->_helper->viewRenderer('by-stat');
    }

    /**
     * Browse rows action.
     */
    public function _browseAction()
    {
        if (!$this->hasParam('sort_field')) {
            $this->setParam('sort_field', $this->_userStatus);
        }
        if (!$this->hasParam('sort_dir')) {
            $this->setParam('sort_dir', 'd');
        }
        parent::browseAction();
    }

    /**
     * Browse rows by field action.
     */
    public function byFieldAction()
    {
        $userStatus = $this->_userStatus;
        $this->setParam('user_status', $userStatus);

        $field = $this->getParam('field');
        if (empty($field) || !in_array($field, array('referrer', 'query', 'user_agent', 'accept_language'))) {
            $field = 'referrer';
            $this->setParam('field', $field);
        }

        $sortField = $this->getParam('sort_field');
        if (empty($sortField) || !in_array($sortField, array($field, 'hits'))) {
            $this->setParam('sort_field', 'hits');
        }
        if (!$this->hasParam('sort_dir')) {
            $this->setParam('sort_dir', 'd');
        }

        // Don't use browseAction, because this is a synthesis, not a list of
        // records (findBy() can't be used)..
        $this->_browseActionByField();

        $totalHits = $this->_helper->db->count(array(
            'user_status' => $userStatus,
        ));

        $totalNotEmpty = $this->_helper->db->count(array(
            'field' => $field,
            'not_empty' => $field,
            'user_status' => $userStatus,
        ));

        switch ($field) {
            case 'referrer': $labelField = __('External Referrers'); break;
            case 'query': $labelField = __('Queries'); break;
            case 'user_agent': $labelField = __('Browsers'); break;
            case 'accept_language': $labelField = __('Accepted Languages'); break;
        }

        $this->view->assign(array(
            'stats_type' => 'field',
            'field' => $field,
            'label_field' => $labelField,
            'total_hits' => $totalHits,
            'total_not_empty' => $totalNotEmpty,
            'user_status' => $userStatus,
        ));
    }

    public function byCollectionAction()
    {
        $db = get_db();

        $year = $this->getParam('year');
        $month = $this->getParam('month');

        $select = new Omeka_Db_Select();
        $select->from(['hits' => $db->Hit]);
        $select->joinInner(['items' => $db->Item], 'items.id = hits.record_id');
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->columns(['items.collection_id', 'COUNT(hits.id) AS total_hits']);
        $select->group('items.collection_id');
        $select->where('hits.record_type = "Item"');
        if ($year) {
            $select->where('YEAR(hits.added) = ?', $year);
        }
        if ($month) {
            $select->where('MONTH(hits.added) = ?', $month);
        }
        $select->order('total_hits');
        $hitsPerCollection = $db->fetchPairs($select);

        $results = [];
        if (plugin_is_active('CollectionTree')) {
            $collections = $db->getTable('Collection')->findAll();
            foreach ($collections as $collection) {
                $hitsInclusive = $this->_getHitsPerCollection($hitsPerCollection, $collection->id);
                if ($hitsInclusive > 0) {
                    $results[] = [
                        'collection' => metadata($collection, ['Dublin Core', 'Title']),
                        'hits' => isset($hitsPerCollection[$collection->id]) ? $hitsPerCollection[$collection->id] : 0,
                        'hitsInclusive' => $hitsInclusive,
                    ];
                }
            }
        } else {
            foreach ($hitsPerCollection as $collectionId => $hits) {
                $collection = $db->getTable('Collection')->findById($collectionId);
                $results[] = [
                    'collection' => metadata($collection, ['Dublin Core', 'Title']),
                    'hits' => $hits,
                ];
            }
        }

        $sortField = $this->getParam('sort_field');
        if (empty($sortField) || !in_array($sortField, array('collection', 'hits', 'hitsInclusive'))) {
            $sortField = 'hitsInclusive';
            $this->setParam('sort_field', $sortField);
        }
        $sortDir = $this->getParam('sort_dir');
        if (empty($sortDir)) {
            $sortDir = 'd';
            $this->setParam('sort_dir', $sortDir);
        }

        usort($results, function ($a, $b) use ($sortField, $sortDir) {
            $cmp = strnatcasecmp($a[$sortField], $b[$sortField]);
            if ($sortDir === 'd') {
                $cmp = -$cmp;
            }
            return $cmp;
        });

        $select = new Omeka_Db_Select();
        $select->from(['hits' => $db->Hit]);
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->distinct();
        $select->columns(['YEAR(hits.added) AS year']);
        $select->order('year desc');
        $years = $db->fetchCol($select);

        $this->view->assign(array(
            'hits' => $results,
            'total_results' => count($results),
            'stats_type' => 'collection',
            'years' => $years,
            'yearFilter' => $year,
            'monthFilter' => $month,
        ));
    }

    protected function _getHitsPerCollection($hitsPerCollection, $collectionId)
    {
        $childrenHits = 0;
        $childCollections = get_db()->getTable('CollectionTree')->getChildCollections($collectionId);
        foreach ($childCollections as $childCollection) {
            $childrenHits += $this->_getHitsPerCollection($hitsPerCollection, $childCollection['id']);
        }

        return (isset($hitsPerCollection[$collectionId]) ? $hitsPerCollection[$collectionId] : 0) + $childrenHits;
    }

    /**
     * Retrieve and render a set of rows for the controller's model.
     *
     * @internal Main difference with browseAction() are that values are not
     * records, but array of synthetic values.
     *
     * @see browseAction()
     *
     * @uses Omeka_Controller_Action_Helper_Db::getDefaultModelName()
     */
    protected function _browseActionByField()
    {
        // Respect only GET parameters when browsing.
        $this->getRequest()->setParamSources(array('_GET'));

        // Inflect the record type from the model name.
        $pluralName = $this->view->pluralize($this->_helper->db->getDefaultModelName());

        $params = $this->getAllParams();
        $recordsPerPage = $this->_getBrowseRecordsPerPage();
        $currentPage = $this->getParam('page', 1);

        // Get the records filtered to Omeka_Db_Table::applySearchFilters().
        $records = $this->_helper->db->getFrequents($params, $recordsPerPage, $currentPage);
        $totalRecords = $this->_helper->db->countFrequents($params);

        // Add pagination data to the registry. Used by pagination_links().
        if ($recordsPerPage) {
            Zend_Registry::set('pagination', array(
                'page' => $currentPage,
                'per_page' => $recordsPerPage,
                'total_results' => $totalRecords,
            ));
        }

        $this->view->assign(array($pluralName => $records, 'total_results' => $totalRecords));
    }

    /**
     * Use global settings for determining browse page limits.
     *
     * @return int
     */
    protected function _getBrowseRecordsPerPage($pluralName = null)
    {
        return is_admin_theme()
            ? (int) get_option('stats_per_page_admin')
            : (int) get_option('stats_per_page_public');
    }
}
