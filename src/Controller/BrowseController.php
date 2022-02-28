<?php declare(strict_types=1);

namespace Statistics\Controller;

use Doctrine\DBAL\Connection;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Statistics\Entity\Stat;

/**
 * Controller to browse Stats.
 */
class BrowseController extends AbstractActionController
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Forward to the summary controller.
     */
    public function indexAction()
    {
        return $this->forward()->dispatch(SummaryController::class);
    }

    /**
     * Redirect to the 'by-page' action.
     */
    public function browseAction()
    {
        $query = $this->params()->fromRoute();
        $query['action'] = 'by-page';
        $isSiteRequest = $this->status()->isSiteRequest();
        return $this->redirect()->toRoute($isSiteRequest ? 'site/statistics/default' : 'admin/statistics/default', $query);
    }

    /**
     * Browse rows by page action.
     */
    public function byPageAction()
    {
        $isSiteRequest = $this->status()->isSiteRequest();
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_PAGE;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_PAGE,
        ]);
        return $view
            ->setTemplate($isSiteRequest ? 'statistics/site/browse/by-stat' : 'statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by resource action.
     */
    public function byResourceAction()
    {
        $isSiteRequest = $this->status()->isSiteRequest();
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_RESOURCE;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_RESOURCE,
        ]);
        return $view
            ->setTemplate($isSiteRequest ? 'statistics/site/browse/by-stat' : 'statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by download action.
     */
    public function byDownloadAction()
    {
        $isSiteRequest = $this->status()->isSiteRequest();
        $defaultSorts = ['anonymous' => 'total_hits_anonymous', 'identified' => 'total_hits_identified'];
        $userStatus = $this->settings()->get('statistics_default_user_status_admin');
        $userStatusBrowse = $defaultSorts[$userStatus] ?? 'total_hits';
        $this->setBrowseDefaults($userStatusBrowse);

        $query = $this->params()->fromQuery();
        $query['type'] = Stat::TYPE_DOWNLOAD;
        $query['user_status'] = $userStatus;

        $response = $this->api()->search('stats', $query);
        $this->paginator($response->getTotalResults());
        $stats = $response->getContent();

        $view = new ViewModel([
            'resources' => $stats,
            'stats' => $stats,
            'userStatus' => $userStatus,
            'type' => Stat::TYPE_DOWNLOAD,
        ]);
        return $view
            ->setTemplate($isSiteRequest ? 'statistics/site/browse/by-stat' : 'statistics/admin/browse/by-stat');
    }

    /**
     * Browse rows by field action.
     */
    public function byFieldAction()
    {
        $settings = $this->settings();
        $isSiteRequest = $this->status()->isSiteRequest();
        $userStatus = $isSiteRequest
            ? $settings->get('statistics_default_user_status_public')
            : $settings->get('statistics_default_user_status_admin');

        $query = $this->params()->fromQuery();

        $field = $query['field'] ?? null;
        if (empty($field) || !in_array($field, ['referrer', 'query', 'user_agent', 'accept_language'])) {
            $field = 'referrer';
            $query['field'] = $field;
        }

        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, [$field, 'hits'])) {
            $query['sort_by'] = 'hits';
        }
        $sortOrder = $query['sort_order'] ?? null;
        if (empty($sortOrder) || !in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $query['sort_order'] = 'desc';
        }

        $currentPage = isset($query['page']) ? (int) $query['page'] : null;
        $resourcesPerPage = $isSiteRequest ? (int) $this->siteSettings()->get('pagination_per_page', 25) : (int) $this->settings()->get('pagination_per_page', 25);

        // Don't use api, because this is a synthesis, not a list of resources.
        /** @var \Statistics\View\Helper\Statistic $statistic */
        $statistic = $this->viewHelpers()->get('statistic');
        $results = $statistic->frequents($query, $resourcesPerPage, $currentPage);
        $totalResults = $statistic->countFrequents($query);
        $totalHits = $this->api()->search('hits', ['user_status' => $userStatus])->getTotalResults();
        $totalNotEmpty = $this->api()->search('hits', ['field' => $field, 'user_status' => $userStatus, 'not_empty' => $field])->getTotalResults();
        $this->paginator($totalResults);

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
            'type' => 'field',
            'field' => $field,
            'labelField' => $labelField,
            'results' => $results,
            'totalHits' => $totalHits,
            'totalNotEmpty' => $totalNotEmpty,
            'userStatus' => $userStatus,
        ]);
        return $view
            ->setTemplate($isSiteRequest ? 'statistics/site/browse/by-field' : 'statistics/admin/browse/by-field');
    }

    public function byItemSetAction()
    {
        // FIXME Stats by item set has not been checked a lot.

        $isSiteRequest = $this->status()->isSiteRequest();
        $query = $this->params()->fromQuery();
        $year = $query['year'] ?? null;
        $month = $query['month'] ?? null;

        $bind = [];
        $types = [];
        $force = $whereYear = $whereMonth = '';
        if ($year || $month) {
            // This is the doctrince hashed name index for the column "created".
            $force = 'FORCE INDEX FOR JOIN (`IDX_5AD22641B23DB7B8`)';
            if ($year) {
                $whereYear = "\nAND YEAR(hit.created) = :year";
                $bind['year'] = $year;
                $types['year'] = \Doctrine\DBAL\ParameterType::INTEGER;
            }
            if ($month) {
                $whereMonth = "\nAND MONTH(hit.created) = :month";
                $bind['month'] = $month;
                $types['month'] = \Doctrine\DBAL\ParameterType::INTEGER;
            }
        }

        $sql = <<<SQL
SELECT item_item_set.item_set_id, COUNT(hit.id) AS total_hits
FROM hit hit $force
JOIN item_item_set ON hit.entity_id = item_item_set.item_id
WHERE hit.entity_name = "items"$whereYear$whereMonth
GROUP BY item_item_set.item_set_id
ORDER BY total_hits
;
SQL;
        $hitsPerItemSet = $this->connection->executeQuery($sql, $bind, $types)->fetchAllKeyValue();

        $api = $this->api();
        $results = [];
        // TODO Check and integrate statistics for item set tree (with performance).
        if (false && $this->plugins()->has('itemSetsTree')) {
            $itemSetIds = $api->search('item_sets', [], ['returnScalar', 'id'])->getContent();
            foreach ($itemSetIds as $itemSetId) {
                $hitsInclusive = $this->getHitsPerItemSet($hitsPerItemSet, $itemSetId);
                if ($hitsInclusive > 0) {
                    $results[] = [
                        'item-set' => $api->read('item_sets', ['id' => $itemSetId])->getContent()->displayTitle(),
                        'hits' => $hitsPerItemSet[$itemSetId] ?? 0,
                        'hitsInclusive' => $hitsInclusive,
                    ];
                }
            }
        } else {
            foreach ($hitsPerItemSet as $itemSetId => $hits) {
                $results[] = [
                    'item-set' => $api->read('item_sets', ['id' => $itemSetId])->getContent()->displayTitle(),
                    'hits' => $hits,
                ];
            }
        }

        $this->paginator(count($results));

        // TODO Manage special sort fields.
        $sortBy = $query['sort_by'] ?? null;
        if (empty($sortBy) || !in_array($sortBy, ['itemSet', 'hits', 'hitsInclusive'])) {
            $sortBy = 'hitsInclusive';
        }
        $sortOrder = $query['sort_order'] ?? null;
        if (empty($sortOrder) || $sortOrder !== 'asc') {
            $sortOrder = 'desc';
        }

        usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
            $cmp = strnatcasecmp($a[$sortBy], $b[$sortBy]);
            return $sortOrder === 'desc' ? -$cmp : $cmp;
        });

        // List of all available years.
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT YEAR(hit.created) AS year')
            ->from('hit', 'hit')
            ->orderBy('year', 'desc');
        $years = $this->connection->executeQuery($qb)->fetchFirstColumn();

        $view = new ViewModel([
            'type' => 'item-set',
            'results' => $results,
            'years' => $years,
            'yearFilter' => $year,
            'monthFilter' => $month,
        ]);
        return $view
            ->setTemplate($isSiteRequest ? 'statistics/site/browse/by-item-set' : 'statistics/admin/browse/by-item-set');
    }

    /**
     * @fixme Finalize integration of item set tree.
     */
    protected function getHitsByItemSet($hitsPerItemSet, $itemSetId): int
    {
        $childrenHits = 0;
        $childItemSetIds = $this->api()->search('item_sets_tree_edge', [], ['returnScalar' => 'id'])->getChildCollections($itemSetId);
        foreach ($childItemSetIds as $childItemSetId) {
            $childrenHits += $this->getHitsPerItemSet($hitsPerItemSet, $childItemSetId);
        }
        return ($hitsPerItemSet[$itemSetId] ?? 0) + $childrenHits;
    }
}
