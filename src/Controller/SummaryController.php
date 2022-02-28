<?php declare(strict_types=1);

namespace Statistics\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Statistics\Entity\Stat;

/**
 * Controller to get summary of Stats.
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
    public function indexAction(): void
    {
        $this->userStatus = $this->status()->isSiteRequest()
            ? $this->settings()->get('statistics_default_user_status_public')
            : $this->settings()->get('statistics_default_user_status_admin');

        $results = [];
        $time = time();

        $results['all'] = $this->statsPeriod();

        $results['today'] = $this->statsPeriod(strtotime('today'));

        $results['history'][$this->translate('Last year')] = $this->statsPeriod( // @translate
            strtotime('-1 year', strtotime(date('Y-1-1', $time))),
            strtotime(date('Y-1-1', $time) . ' - 1 second')
        );
        $results['history'][$this->translate('Last month')] = $this->statsPeriod(
            strtotime('-1 month', strtotime(date('Y-m-1', $time))),
            strtotime(date('Y-m-1', $time) . ' - 1 second')
        );
        $results['history'][$this->translate('Last week')] = $this->statsPeriod(
            strtotime("previous week"),
            strtotime("previous week + 6 days")
        );
        $results['history'][$this->translate('Yesterday')] = $this->statsPeriod(
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            strtotime('-1 day', strtotime(date('Y-m-d', $time)))
        );

        $results['current'][$this->translate('This year')] = // @translate
            $this->statsPeriod(strtotime(date('Y-1-1', $time)));
        $results['current'][$this->translate('This month')] =  // @translate
            $this->statsPeriod(strtotime(date('Y-m-1', $time)));
        $results['current'][$this->translate('This week')] = // @translate
            $this->statsPeriod(strtotime('this week'));
        $results['current'][$this->translate('This day')] = // @translate
            $this->statsPeriod(strtotime('today'));

        foreach ([365 => null, 30 => null, 7 => null, 1 => null] as $start => $endPeriod) {
            $startPeriod = strtotime("- {$start} days");
            $label = ($start == 1)
                ? $this->translate('Last 24 hours') // @translate
                : $this->translate('Last %s days', $start); // @translate
            $results['rolling'][$label] = $this->statsPeriod($startPeriod, $endPeriod);
        }

        if (is_allowed('Stats_Browse', 'by-page')) {
            $results['most_viewed_pages'] = $tableStat->getMostViewedPages(null, $userStatus, 10);
        }
        if (is_allowed('Stats_Browse', 'by-record')) {
            $results['most_viewed_records'] = $tableStat->getMostViewedResources(null, $userStatus, 10);
            $results['most_viewed_collections'] = $tableStat->getMostViewedResources('Collection', $userStatus, 10);
        }
        if (is_allowed('Stats_Browse', 'by-download')) {
            $results['most_viewed_downloads'] = $tableStat->getMostViewedDownloads($userStatus, 10);
        }

        if (is_allowed('Stats_Browse', 'by-field')) {
            $results['most_frequent_fields'] = [];
            $results['most_frequent_fields']['referrer'] = $tableHit->getMostFrequents('referrer', $userStatus, 10);
            $results['most_frequent_fields']['query'] = $tableHit->getMostFrequents('query', $userStatus, 10);
            $results['most_frequent_fields']['user_agent'] = $tableHit->getMostFrequents('user_agent', $userStatus, 10);
            $results['most_frequent_fields']['accept_language'] = $tableHit->getMostFrequents('accept_language', $userStatus, 10);
        }

        $this->view->assign([
            'results' => $results,
            'user_status' => $userStatus,
        ]);
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

        $result = [];
        if (is_admin_theme()) {
            $counts = $tableHit->getCountsByUserStatus($params);
            $result['anonymous'] = $counts['hits_anonymous'];
            $result['identified'] = $counts['hits_identified'];
            $result['total'] = $result['anonymous'] + $result['identified'];
        } else {
            $params['user_status'] = $userStatus;
            $result = $tableHit->count($params);
        }

        return $result;
    }
}
