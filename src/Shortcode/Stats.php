<?php declare(strict_types=1);

namespace Stats\Shortcode;

use Shortcode\Shortcode\AbstractShortcode;

class Stats extends AbstractShortcode
{
    public function render(array $args = []): string
    {
        if ($this->shortcodeName === 'stats_total') {
            return $this->renderStatsTotal($args);
        } elseif ($this->shortcodeName === 'stats_position') {
            return $this->renderStatsPosition($args);
        } elseif ($this->shortcodeName === 'stats_vieweds') {
            return $this->renderStatsVieweds($args);
        } else {
            return '';
        }
    }

    /**
     * Shortcode to display total hits of one or multiple pages or resources.
     *
     * If resource(s) is set, don't look for url(s).
     */
    protected function renderStatsTotal(array $args): string
    {
        $type = $args['type'] ?? null;

        // TODO There may be multiple resource names.
        $resourceName = $args['resource'] ?? $args['resource_type'] ?? $args['record_type'] ?? null;
        if ($resourceName) {
            $resourceName = strtolower($resourceName);
        }

        $resourceId = $resourceName
            ? $args['id'] ?? $args['resource_id'] ?? $args['record_id'] ?? null
            : null;

        // Search by resource.
        if ($resourceId) {
            $result = $type === 'download'
                ? $this->view->statistic()->totalDownload($resourceId)
                : $this->view->statistic()->totalResource($resourceName, $resourceId);
        }
        // Search by resource type.
        elseif ($resourceName) {
            $result = $this->view->statistic()->totalResourceType($resourceName);
        }
        // Search by url.
        else {
            $url = $args['url'] ?? $this->view->url(null, [], true);
            $result = $this->view->statistic()->totalPage($url);
        }

        // Don't return null.
        return '<span class="stats-data stats-hits">'
            . (int) $result
            . '</span>';
    }

    /**
     * Shortcode to display the position of the page or resource (most viewed).
     */
    protected function renderStatsPosition(array $args): string
    {
        $type = $args['type'] ?? null;

        // Unlike StatsTotal, position of multiple resource type is meaningless.
        $resourceName = $args['resource'] ?? $args['resource_type'] ?? $args['record_type'] ?? null;
        if ($resourceName) {
            $resourceName = strtolower($resourceName);
        }

        $resourceId = $resourceName
            ? $args['id'] ?? $args['resource_id'] ?? $args['record_id'] ?? null
            : null;

        // Search by resource.
        if ($resourceId) {
            $result = $type === 'download'
                ? $this->view->statistic()->positionDownload($resourceId)
                : $this->view->statistic()->positionResource($resourceName, $resourceId);
        }
        // Search by url.
        else {
            $url = $args['url'] ?? $this->view->url(null, [], true);
            $result = $this->view->statistic()->positionPage($url);
        }

        // Don't return null.
        return '<span class="stats-data stats-position">'
            . (int) $result
            . '</span>';
    }

    /**
     * Shortcode to get the viewed pages or resources.
     */
    protected function renderStatsVieweds(array $args): string
    {
        $type = $args['type'] ?? null;
        $sort = isset($args['sort']) && $args['sort'] === 'last' ? 'last' : 'most';
        $limit = isset($args['number']) ? (int) $args['number'] : 10;
        $offset = isset($args['offset']) ? (int) $args['offset'] : null;

        return $type
            // Search by resource type.
            ? $this->view->statistic()->viewedResources($type, $sort, null, $limit, $offset, true)
            // Search in all pages.
            : $this->view->statistic()->viewedPages(null, $sort, null, $limit, $offset, true);
    }
}
