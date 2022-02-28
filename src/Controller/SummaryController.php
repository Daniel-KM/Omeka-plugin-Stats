<?php declare(strict_types=1);

namespace Statistics\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Controller to get summary of Stats.
 *
 * @todo Merge with BrowseController.
 */
class SummaryController extends AbstractActionController
{
    /**
     * @var string
     */
    protected $userStatus;

    /**
     * Index action.
     */
    public function indexAction()
    {
        $isAdminRequest = $this->status()->isAdminRequest();
        $this->userStatus = $isAdminRequest
           ? $this->settings()->get('statistics_default_user_status_admin')
            : $this->settings()->get('statistics_default_user_status_public');

        $results = [];
        $time = time();

        $translate = $this->plugins->get('translate');

        $results['all'] = $this->statsPeriod();

        $results['today'] = $this->statsPeriod(strtotime('today'));

        $results['history'][$translate('Last year')] = $this->statsPeriod( // @translate
            strtotime('-1 year', strtotime(date('Y-1-1', $time))),
            strtotime(date('Y-1-1', $time) . ' - 1 second')
        );
        $results['history'][$translate('Last month')] = $this->statsPeriod( // @translate
            strtotime('-1 month', strtotime(date('Y-m-1', $time))),
            strtotime(date('Y-m-1', $time) . ' - 1 second')
        );
        $results['history'][$translate('Last week')] = $this->statsPeriod( // @translate
            strtotime("previous week"),
            strtotime("previous week + 6 days")
        );
        $results['history'][$translate('Yesterday')] = $this->statsPeriod( // @translate
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            strtotime('-1 day', strtotime(date('Y-m-d', $time)))
        );

        $results['current'][$translate('This year')] = // @translate
            $this->statsPeriod(strtotime(date('Y-1-1', $time)));
        $results['current'][$translate('This month')] =  // @translate
            $this->statsPeriod(strtotime(date('Y-m-1', $time)));
        $results['current'][$translate('This week')] = // @translate
            $this->statsPeriod(strtotime('this week'));
        $results['current'][$translate('This day')] = // @translate
            $this->statsPeriod(strtotime('today'));

        foreach ([365 => null, 30 => null, 7 => null, 1 => null] as $start => $endPeriod) {
            $startPeriod = strtotime("- {$start} days");
            $label = ($start == 1)
                ? $translate('Last 24 hours') // @translate
                : sprintf($translate('Last %s days'), $start); // @translate
            $results['rolling'][$label] = $this->statsPeriod($startPeriod, $endPeriod);
        }

        if ($this->userIsAllowed('Statistics\Controller\Browse', 'by-page')) {
            /** @var \Statistics\View\Helper\Statistic $statistic */
            $statistic = $this->viewHelpers()->get('statistic');
            $results['most_viewed_pages'] = $statistic->mostViewedPages(null, $this->userStatus, 10);
            $results['most_viewed_resources'] = $statistic->mostViewedResources(null, $this->userStatus, 10);
            $results['most_viewed_item_sets'] = $statistic->mostViewedResources('item_sets', $this->userStatus, 10);
            $results['most_viewed_downloads'] = $statistic->mostViewedDownloads($this->userStatus, 10);
            $results['most_frequent_fields']['referrer'] = $statistic->mostFrequents('referrer', $this->userStatus, 10);
            $results['most_frequent_fields']['query'] = $statistic->mostFrequents('query', $this->userStatus, 10);
            $results['most_frequent_fields']['user_agent'] = $statistic->mostFrequents('user_agent', $this->userStatus, 10);
            $results['most_frequent_fields']['accept_language'] = $statistic->mostFrequents('accept_language', $this->userStatus, 10);
        }

        $view = new ViewModel([
            'results' => $results,
            'userStatus' => $this->userStatus,
        ]);

        return $view
            ->setTemplate($isAdminRequest ? 'statistics/admin/summary/index' : 'statistics/site/summary/index');
    }

    /**
     * Helper to get all stats of a period.
     *
     * @param int $startPeriod Number of days before today (default is all).
     * @param int $endPeriod Number of days before today (default is now).
     * @return array
     */
    protected function statsPeriod(?int $startPeriod = null, ?int $endPeriod = null): array
    {
        $params = [];
        if ($startPeriod) {
            $params['since'] = date('Y-m-d 00:00:00', $startPeriod);
        }
        if ($endPeriod) {
            $params['until'] = date('Y-m-d 23:59:59', $endPeriod);
        }

        $api = $this->api();
        if ($this->status()->isAdminRequest()) {
            // TODO Use a single query (see version for Omeka Classic).
            $params['user_status'] = 'anonymous';
            $anonymous = $api->search('hits', $params)->getTotalResults();
            $params['user_status'] = 'identified';
            $identified = $api->search('hits', $params)->getTotalResults();
            return [
                'anonymous' => $anonymous,
                'identified' => $identified,
                'total' => $anonymous + $identified,
            ];
        }

        $params['user_status'] = $this->userStatus ?: 'total';
        return [
            'total' => $api->search('hits', $params)->getTotalResults(),
        ];
    }
}
