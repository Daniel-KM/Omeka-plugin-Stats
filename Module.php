<?php declare(strict_types=1);

namespace Statistics;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Request;
use Omeka\Api\Representation\AbstractResourceRepresentation;

/**
 * Stats
 *
 * Logger that counts views of pages and resources and makes stats about usage
 * and users of the site.
 *
 * @copyright Daniel Berthereau, 2014-2022
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $acl
            // These rights may be too much large: it's viewable by api and
            // contains sensitive informations.
            // FIXME Add a filter in the api to limit output.
            ->allow(
                null,
                [
                    \Statistics\Entity\Hit::class,
                    \Statistics\Entity\Stat::class,
                ],
                ['read', 'create', 'search']
            )
            ->allow(
                null,
                [
                    \Statistics\Api\Adapter\HitAdapter::class,
                    \Statistics\Api\Adapter\StatAdapter::class,
                ],
                ['read', 'create', 'search']
            )
            ->allow(
                null,
                ['Statistics\Controller\Download']
            )
        ;
        // Only admins are allowed to browse stats.
        // The individual stats are always displayed in admin.
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'logCurrentPage']
        );

        // Events for the public front-end.
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.after',
            [$this, 'displayPublic']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Page',
            'view.show.after',
            [$this, 'displayPublic']
        );

        // Events for the admin front-end.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'viewDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.details',
            [$this, 'viewDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.details',
            [$this, 'viewDetails']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Index',
            'view.browse.after',
            [$this, 'filterAdminDashboardPanels']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }

    /**
     * Log the hit on the current page.
     */
    public function logCurrentPage()
    {
        // Don't log admin pages.
        $services = $this->getServiceLocator();

        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');
        if ($status->isAdminRequest()) {
            return;
        }

        // For performance, use the adapter directly, not the api.
        // TODO Use direct sql query to store hits?
        /** @var \Statistics\Api\Adapter\HitAdapter $adapter */
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('hits');

        $includeBots = (bool) $services->get('Omeka\Settings')->get('statistics_include_bots');
        if (empty($includeBots)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($adapter->isBot($userAgent)) {
                return;
            }
        }

        $request = new Request(Request::CREATE, 'hits');
        $request
            ->setOption('initialize', false)
            ->setOption('finalize', false)
            ->setOption('returnScalar', 'id')
        ;
        // The entity manager is automatically flushed by default.
        $adapter->create($request);
    }

    public function displayPublic(Event $event)
    {
        $view = $event->getTarget();
        $resource = $view->vars()->offsetGet('resource');
        echo $view->statistic()->textResource($resource);
    }

    public function viewDetails(Event $event)
    {
        $view = $event->getTarget();
        $representation = $event->getParam('entity');
        $statTitle = $view->translate('Statistics'); // @translate
        $statText = $this->resultResource($view, $representation);
        $html = <<<HTML
<div class="meta-group">
    <h4>$statTitle</h4>
    $statText
</div>

HTML;
        echo $html;
    }

    protected function resultResource(PhpRenderer $view, AbstractResourceRepresentation $resource)
    {
        $plugins = $view->getHelperPluginManager();
        $statistic = $plugins->get('statistic');
        $translate = $plugins->get('translate');

        $html = '<ul>';
        $html .= '<li>';
        $html .= sprintf($translate('Views: %d (%d anonymous / %d identified users)'), // @translate
            $statistic->totalResource($resource),
            $statistic->totalResource($resource, 'anonymous'),
            $statistic->totalResource($resource, 'identified'));
        $html .= '</li>';
        $html .= '<li>';
        $html .= sprintf($translate('Position: %d (%d anonymous / %d identified users)'), // @translate
            $statistic->positionResource($resource),
            $statistic->positionResource($resource, 'anonymous'),
            $statistic->positionResource($resource, 'identified'));
        $html .= '</li>';
        $html .= '</ul>';
        return $html;
    }

    public function filterAdminDashboardPanels(Event $event): void
    {
        $view = $event->getTarget();
        $plugins = $view->getHelperPluginManager();
        $userIsAllowed = $plugins->get('userIsAllowed');

        $userIsAllowedSummary = $userIsAllowed('Statistics\Controller\Summary', 'index');
        $userIsAllowedBrowse = $userIsAllowed('Statistics\Controller\Browse', 'browse');
        if (!$userIsAllowedSummary && !$userIsAllowedBrowse) {
            return;
        }

        $services = $this->getServiceLocator();
        $url = $plugins->get('url');
        $api = $services->get('Omeka\ApiManager');
        $escape = $plugins->get('escapeHtml');
        $statistic = $plugins->get('statistic');
        $settings = $services->get('Omeka\Settings');
        $translate = $plugins->get('translate');
        $escapeAttr = $plugins->get('escapeHtmlAttr');

        $userStatus = $settings->get('statistics_default_user_status_admin');
        $totalHits = $api->search('hits', ['user_status' => $userStatus])->getTotalResults();
        $entityResource = null;

        $statsTitle = $translate('Statistics'); // @translate
        $html = <<<HTML
<div id="stats" class="panel">
    <h2>$statsTitle</h2>

HTML;

        if ($userIsAllowedSummary) {
            $statsSummaryUrl = $url('admin/statistics', [], true);
            $statsSummaryText = sprintf($translate('Total Hits: %d'), $totalHits); // @translate
            $lastTexts = [
                30 => $translate('Last 30 days'),
                7 => $translate('Last 7 days'),
                1 => $translate('Last 24 hours'),
            ];
            $lastTotals = [
                30 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-30 days')), 'user_status' => $userStatus])->getTotalResults(),
                7 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-7 days')), 'user_status' => $userStatus])->getTotalResults(),
                1 => $api->search('hits', ['since' => date('Y-m-d', strtotime('-1 days')), 'user_status' => $userStatus])->getTotalResults(),
            ];
            $html .= <<<HTML
    <h4><a href="$statsSummaryUrl">$statsSummaryText</a></h4>
    <ul>
        <li>$lastTexts[30] : $lastTotals[30]</li>
        <li>$lastTexts[7] : $lastTotals[7]</li>
        <li>$lastTexts[1] : $lastTotals[1]</li>
    </ul>

HTML;
        }

        if ($userIsAllowedBrowse) {
            $statsBrowseUrl = $url('admin/statistics/default', ['action' => 'by-page'], true);
            $statsBrowseText = $translate('Most viewed public pages'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            /** @var \Statistics\Api\Representation\StatRepresentation[] $results */
            $stats = $statistic->mostViewedPages(null, $userStatus, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $html .= '<ol>';
                foreach ($stats as $stat) {
                    $html .= '<li>';
                    $html .= sprintf($translate('%s (%d views)'),
                        // $stat->getPositionPage(),
                        '<a href="' . $escapeAttr($stat->hitUrl()) . '">' . $escape($stat->hitUrl()) . '</a>',
                        $stat->totalHits($userStatus)
                    );
                    $html .= '</li>';
                }
                $html .= '</ol>';
            }

            $statsBrowseUrl = $url('admin/statistics/default', ['action' => 'by-resource'], true);
            $statsBrowseText = $translate('Most viewed public item'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $statistic->mostViewedResources('items', $userStatus, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d views)'), // @translate
                    $entityResource = $stat->entityResource() ? $entityResource->linkRaw() : $translate('Unavailable'), // @translate
                    $stat->totalHits($userStatus));
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/statistics/default', ['action' => 'by-resource'], true);
            $statsBrowseText = $translate('Most viewed public item set'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $statistic->mostViewedResources('item_sets', $userStatus, 5);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d views)'), // @translate
                    $entityResource = $stat->entityResource() ? $entityResource->linkRaw() : $translate('Unavailable'), // @translate
                    $stat->totalHits($userStatus));
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/statistics/default', ['action' => 'by-download'], true);
            $statsBrowseText = $translate('Most downloaded file'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            $stats = $statistic->mostViewedDownloads($userStatus, 1);
            if (empty($stats)) {
                $html .= '<p>' . $translate('None') . '</p>';
            } else {
                $stat = reset($stats);
                $html .= '<ul>';
                $html .= sprintf($translate('%s (%d downloads)'), // @translate
                    $entityResource = $stat->entityResource() ? $entityResource->linkRaw() : $translate('Unavailable'), // @translate
                    $stat->totalHits($userStatus));
                $html .= '</ul>';
            }

            $statsBrowseUrl = $url('admin/statistics/default', ['action' => 'by-field'], true);
            $statsBrowseText = $translate('Most frequent fields'); // @translate
            $html .= '<h4><a href="' . $statsBrowseUrl . '">' . $statsBrowseText . '</a></h4>';
            /** @var \Statistics\Api\Representation\StatRepresentation[] $results */
            foreach ([
                'referrer' => $translate('Referrer'), // @translate
                'query' => $translate('Query'), // @translate
                'user_agent' => $translate('User Agent'), // @translate
                'accept_language' => $translate('Accepted Language'), // @translate
            ] as $field => $label) {
                $hits = $statistic->mostFrequents($field, $userStatus, 1);
                $html .= '<li>';
                if (empty($hits)) {
                    $html .= sprintf($translate('%s: None'), $label);
                } else {
                    $hit = reset($hits);
                    $html .= sprintf('%s: %s (%d%%)', sprintf('<a href="%s">%s</a>', $url('admin/statistics/default', ['action' => 'by-field'], true) . '?field=' . $field, $label), $hit->$field(), $hit->totalPage($userStatus) * 100 / $totalHits);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        echo $html;
    }
}
