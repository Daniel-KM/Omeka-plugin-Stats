<?php declare(strict_types=1);

namespace Statistics\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Statistics\Entity\Stat;

/**
 * Controller to browse Stats.
 */
class BrowseController extends AbstractActionController
{
    /**
     * Forward to the 'browse by page' action
     */
    public function indexAction()
    {
        return $this->forward()->dispatch(SummaryController::class);
    }

    /**
     * Forward to the 'by-page' action
     */
    public function browseAction()
    {
        $view = $this->byPageAction();
        return $view
            ->setTemplate('statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by page action.
     */
    public function byPageAction()
    {
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $response = $this->api()->search('stats', ['type' => Stat::TYPE_PAGE, 'user_status' => $userStatus]);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate('statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by resource action.
     */
    public function byResourceAction()
    {
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $response = $this->api()->search('stats', ['type' => Stat::TYPE_RESOURCE, 'user_status' => $userStatus]);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate('statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by download action.
     */
    public function byDownloadAction()
    {
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $response = $this->api()->search('stats', ['type' => Stat::TYPE_DOWNLOAD, 'user_status' => $userStatus]);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate('statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by field action.
     */
    public function byFieldAction()
    {
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');

        $params = $this->params()->fromQuery();

        $field = $params['field'] ?? null;
        if (empty($field) || !in_array($field, ['referrer', 'query', 'user_agent', 'accept_language'])) {
            $field = 'referrer';
            $params['field'] = $field;
        }

        $sortBy = $params['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, [$field, 'hits'])) {
            $sortBy = 'hits';
            $params['sort_by'] = 'hits';
        }
        $sortOrder = $params['sort_order'] ?? null;
        if (empty($sortOrder) || !in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
            $params['sort_order'] = 'desc';
        }

        // Don't use api, because this is a synthesis, not a list of resources.
        $this->browseActionByField($params);

        $totalHits = $this->api()->search('hits', ['user_status' => $userStatus])->getTotalResults();

        // TODO There is a special filter for field "referrer": "NOT LIKE ?", WEB_ROOT . '/%'."
        $totalNotEmpty = $this->api()->search('hits', ['field' => $field, 'user_status' => $userStatus, 'not_empty' => $field])->getTotalResults();

        switch ($field) {
            default:
            case 'referrer':
                $labelField = $this->translate('External Referrers'); // @translate
                break;
            case 'query':
                $labelField = $this->translate('Queries'); // @translate
                break;
            case 'user_agent':
                $labelField = $this->translate('Browsers'); // @translate
                break;
            case 'accept_language':
                $labelField = $this->translate('Accepted Languages'); // @translate
                break;
        }

        $view = new ViewModel([
            'statsType' => 'field',
            'field' => $field,
            'labelField' => $labelField,
            'totalHits' => $totalHits,
            'totalNotEmpty' => $totalNotEmpty,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate('statistics/admin/browse/by-field');
    }

    public function byItemSetAction(): void
    {
        $db = get_db();

        $year = $this->getParam('year');
        $month = $this->getParam('month');

        $sql = "SELECT items.collection_id, COUNT(hits.id) AS total_hits FROM {$db->Hit} hits";
        if ($year || $month) {
            $sql .= ' FORCE INDEX FOR JOIN (added)';
        }
        $sql .= " JOIN {$db->Item} items ON (hits.resource_id = items.id)";
        $sql .= ' WHERE hits.resource_type = "Item"';
        if ($year) {
            $sql .= ' AND YEAR(hits.added) = ' . $db->quote($year, Zend_Db::INT_TYPE);
        }
        if ($month) {
            $sql .= ' AND MONTH(hits.added) = ' . $db->quote($month, Zend_Db::INT_TYPE);
        }
        $sql .= ' GROUP BY items.collection_id ORDER BY total_hits';
        $hitsPerCollection = $db->fetchPairs($sql);

        $results = [];
        if (plugin_is_active('CollectionTree')) {
            $collections = $db->getTable('Collection')->findAll();
            foreach ($collections as $collection) {
                $hitsInclusive = $this->_getHitsPerCollection($hitsPerCollection, $collection->id);
                if ($hitsInclusive > 0) {
                    $results[] = [
                        'collection' => metadata($collection, ['Dublin Core', 'Title']),
                        'hits' => $hitsPerCollection[$collection->id] ?? 0,
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
        if (empty($sortField) || !in_array($sortField, ['collection', 'hits', 'hitsInclusive'])) {
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

        $this->view->assign([
            'hits' => $results,
            'total_results' => count($results),
            'statistics_type' => 'collection',
            'years' => $years,
            'yearFilter' => $year,
            'monthFilter' => $month,
        ]);
    }

    protected function _getHitsPerCollection($hitsPerCollection, $collectionId)
    {
        $childrenHits = 0;
        $childCollections = get_db()->getTable('CollectionTree')->getChildCollections($collectionId);
        foreach ($childCollections as $childCollection) {
            $childrenHits += $this->_getHitsPerCollection($hitsPerCollection, $childCollection['id']);
        }

        return ($hitsPerCollection[$collectionId] ?? 0) + $childrenHits;
    }

    /**
     * Retrieve and render a set of rows for the controller's model.
     *
     * Here, values are not resources, but array of synthetic values.
     */
    protected function browseActionByField(array $params): void
    {
        $resourcesPerPage = $this->getBrowseResourcesPerPage();
        $currentPage = empty($params['page']) ? 1 : (int) $params['page'];

        $statistic = $this->viewHelpers('statistic');

        $resources = $statistic->getFrequents($params, $resourcesPerPage, $currentPage);
        $totalResources = $statistic->countFrequents($params);

        // Add pagination data to the registry. Used by pagination_links().
        if ($resourcesPerPage) {
            Zend_Registry::set('pagination', [
                'page' => $currentPage,
                'per_page' => $resourcesPerPage,
                'total_results' => $totalResources,
            ]);
        }

        $this->view->assign([$pluralName => $resources, 'total_results' => $totalResources]);
    }

    /**
     * Use global settings for determining browse page limits.
     */
    protected function getBrowseResourcesPerPage(): int
    {
        return $this->status()->isAdminRequest()
            ? (int) $this->settings()->get('statistics_per_page_admin')
            : (int) $this->settings()->get('statistics_per_page_public');
    }
}
