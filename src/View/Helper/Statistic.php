<?php declare(strict_types=1);

namespace Statistics\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Statistics\Api\Adapter\HitAdapter;
use Statistics\Api\Adapter\StatAdapter;
use Statistics\Entity\Stat;

/**
 * Helper to get some public stats.
 *
 * Note: There is no difference between total of page or download, because each
 * url is unique, but there are differences between positions and viewed pages
 * and downloaded files lists.
 */
class Statistic extends AbstractHelper
{
    /**
     * @var \Statistics\Api\Adapter\HitAdapter
     */
    protected $hitAdapter;

    /**
     * @var \Statistics\Api\Adapter\StatAdapter
     */
    protected $statAdapter;

    public function __construct(HitAdapter $hitAdapter, StatAdapter $statAdapter)
    {
        $this->hitAdapter = $hitAdapter;
        $this->statAdapter = $statAdapter;
    }

    /**
     * Get the stats.
     *
     * @return self
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Hit a new page (to use only with external plugins for not managed urls).
     *
     * No filter is applied to get the eventual resource.
     *
     * @param string $url Url
     * @param \Omeka\Api\Representation\AbstractRepresentation|array $resource
     * If array, contains the resource type (api name) and resource id.
     */
    public function newHit(string $url, $resource = null): void
    {
        if (empty($url)) {
            return;
        }

        $pos = strpos($url, '?');
        if ($pos && $pos !== strlen($url)) {
            $query = substr($url, $pos + 1);
            $cleanedUrl = $this->checkAndCleanUrl(substr($url, 0, $pos));
        } else {
            $query = '';
            $cleanedUrl = $this->checkAndCleanUrl($url);
        }

        $resource = $this->checkAndPrepareResource($resource);
        $user = $this->view->identity();

        $request = new \Omeka\Api\Request(\Omeka\Api\Request::CREATE, 'hits');
        $request
            ->setContent([
                'url' => $cleanedUrl,
                'entity_name' => $resource['type'],
                'entity_id' => $resource['id'],
                'user_id' => $user ? $user->getId() : 0,
                'query' => $query,
            ])
            ->setOption('initialize', false)
            ->setOption('finalize', false)
            ->setOption('returnScalar', 'id')
        ;
        $this->hitAdapter->create($request);
    }

    /**
     * Get the count of hits of the page.
     *
     * @param string $url Url Current url if null.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return int
     */
    public function totalPage(?string $url = null, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        if (is_null($url)) {
            $url = $this->currentUrl();
        }
        return $this->statAdapter->totalPage($url, $userStatus);
    }

    /**
     * Get the count of hits of the resource.
     *
     * @param Resource|array $resource If array, contains resource type and resource id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function totalResource($resource, ?string $userStatus = null): int
    {
        $entity = $this->checkAndPrepareResource($resource);
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->statAdapter->totalResource($entity['type'], $entity['id'], $userStatus);
    }

    /**
     * Get the count of hits of the resource type.
     *
     * @param Resource|array $resource If array, contains resource type and resource id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function totalResourceType($resourceType, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->statAdapter->totalResourceType($resourceType, $userStatus);
    }

    /**
     * Get the count of hits of a resource or sub-resource.
     *
     * @param Resource|string|int $value If string or numeric, url or id of the
     * downloaded  file. If Item, returns total of dowloaded files of this Item.
     * If Collection, returns total of downloaded files of all items. If File,
     * returns total of downloads of this file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function totalDownload($value, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->statAdapter->totalDownload($value, $userStatus);
    }

    /**
     * Get the position of hits of the page.
     *
     * @param string $url Url Current url if null.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function positionPage(?string $url = null, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        if (is_null($url)) {
            $url = $this->currentUrl();
        }
        // Call positionHits() and not positionPage() to simplify process of
        // page or download. The check is made later.
        return $this->statAdapter->positionHits(['url' => $url], $userStatus);
    }

    /**
     * Get the position of hits of the resource (by resource type).
     *
     * @param Resource|array $resource If array, contains resource type and resource id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function positionResource($resource, ?string $userStatus = null): int
    {
        $entity = $this->checkAndPrepareResource($resource);
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->statAdapter->positionResource($entity['type'], $entity['id'], $userStatus);
    }

    /**
     * Get the position of hits of the download.
     *
     * @todo Position of user is currently unavailable.
     *
     * @param Resource|string|int $value If string or numeric, url or id of the
     * downloaded  file. If Item, returns position of dowloaded files of this
     * Item. If Collection, returns position of downloaded files of all items.
     * If File, returns position of downloads of this file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     */
    public function positionDownload($value, ?string $userStatus = null): int
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->statAdapter->positionDownload($value, $userStatus);
    }

    /**
     * Get viewed pages.
     *
     * @param null|bool $hasResource Null for all pages, boolean to set with or
     * without resource.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Offfset to set page to retrieve.
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedPages(?bool $hasResource = null, ?string $sort = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null, bool $asHtml = true)
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stats = $sort === 'last'
            ? $this->statAdapter->lastViewedPages($hasResource, $userStatus, $limit, $page)
            : $this->statAdapter->mostViewedPages($hasResource, $userStatus, $limit, $page);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_PAGE, $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed resources.
     *
     * @param Resource|array $resourceType If array, contains resource type.
     * Can be empty, "all", "none", "page" or "download" too.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Offfset to set page to retrieve.
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedResources($resourceType, ?string $sort = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null, bool $asHtml = true)
    {
        // Manage exceptions.
        if (empty($resourceType) || $resourceType === 'all' || (is_array($resourceType) && in_array('all', $resourceType))) {
            $resourceType = null;
        } elseif ($resourceType === 'none' || (is_array($resourceType) && in_array('none', $resourceType))) {
            return $this->viewedPages(false, $sort, $userStatus, $limit, $page, $asHtml);
        } elseif ($resourceType === Stat::TYPE_PAGE || (is_array($resourceType) && in_array(Stat::TYPE_PAGE, $resourceType))) {
            return $this->viewedPages(null, $sort, $userStatus, $limit, $page, $asHtml);
        } elseif ($resourceType === Stat::TYPE_DOWNLOAD || (is_array($resourceType) && in_array(Stat::TYPE_DOWNLOAD, $resourceType))) {
            return $this->viewedDownloads($sort, $userStatus, $limit, $page, $asHtml);
        }

        $userStatus = $this->normalizeUserStatus($userStatus);
        $stats = $sort === 'last'
            ? $this->statAdapter->lastViewedResources($resourceType, $userStatus, $limit, $page)
            : $this->statAdapter->mostViewedResources($resourceType, $userStatus, $limit, $page);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_RESOURCE, $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed downloads.
     *
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Offfset to set page to retrieve.
     * @param bool $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewedDownloads(?string $sort = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null, bool $asHtml = true)
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stats = $sort === 'last'
            ? $this->statAdapter->lastViewedDownloads($userStatus, $limit, $page)
            : $this->statAdapter->mostViewedDownloads($userStatus, $limit, $page);

        return $asHtml
            ? $this->viewedHtml($stats, Stat::TYPE_DOWNLOAD, $sort, $userStatus)
            : $stats;
    }

    /**
     * Helper to get string from list of stats.
     *
     * @param array $stats Array of stats.
     * @param string $type "page", "resource" or "download".
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string html
     */
    protected function viewedHtml($stats, $type, $sort, $userStatus): string
    {
        if (empty($stats)) {
            return '<div class="stats">' . $this->view->translate('None.') . '</div>';
        }

        // TODO Use partial loop.
        $partial = $this->view->plugin('partial');
        $html = '';
        foreach ($stats as $key => $stat) {
            $params = [
                'type' => $type,
                'stat' => $stat,
                'sort' => $sort,
                'userStatus' => $userStatus,
                'position' => $key + 1,
            ];
            $html .= $partial('common/statistics-single', $params);
        }
        return $html;
    }

    /**
     * Get the most viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     * with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        return $this->statAdapter->mostViewedPages($hasResource, $userStatus, $limit, $page);
    }

    /**
     * Get the most viewed specified resources.
     *
     * Zero viewed resources are never returned.
     *
     * @param string|array $entityName If array, may contain multiple
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedResources($entityName = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        return $this->statAdapter->mostViewedResources($entityName, $userStatus, $limit, $page);
    }

    /**
     * Get the most downloaded files.
     *
     * Zero viewed downloads are never returned.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return \Statistics\Api\Representation\StatRepresentation[]
     */
    public function mostViewedDownloads(?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        return $this->statAdapter->mostViewedDownloads($userStatus, $limit, $page);
    }

    /**
     * Retrieve a count of distinct rows for a field. Empty is not count.
     *
     * @param array $query optional Set of search filters upon which to base
     * the count.
     */
    public function countFrequents(array $query = []): int
    {
        return $this->hitAdapter->countFrequents($query);
    }

    /**
     * Get the most frequent data in a field. Empty values are never returned.
     *
     * The main difference with search() is that values are not resources, but
     * array of synthetic values.
     *
     * @param array $params A set of parameters by which to filter the objects
     *   that get returned from the database. It should contains a 'field' for
     *   the name of the column to evaluate.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array Data and total hits.
     */
    public function frequents(array $query = [], ?int $limit = null, ?int $page = null): array
    {
        return $this->hitAdapter->frequents($query, $limit, $page);
    }

    /**
     * Get the most frequent data in a field.
     *
     * @param string $field Name of the column to evaluate.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     * @return array Data and total of the according total hits.
     */
    public function mostFrequents(string $field, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        return $this->hitAdapter->mostFrequents($field, $userStatus, $limit, $page);
    }

    /**
     * Get the stat view for the selected page.
     *
     * @param string $url Url (current url if empty).
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textPage(?string $url = null, ?string $userStatus = null): string
    {
        if (empty($url)) {
            $url = $this->currentUrl();
        }
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stat = $this->view->api()->searchOne('stats', ['url' => $url, 'type' => Stat::TYPE_PAGE])->getContent();
        return $this->view->partial('common/statistics-value', [
            'type' => Stat::TYPE_PAGE,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Get the stat view for the selected resource.
     *
     * @param Resource|array $resource If array, contains resource type and resource id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textResource($resource, ?string $userStatus = null): string
    {
        // Check and get resource.
        $resource = $this->checkAndPrepareResource($resource);
        if (empty($resource['type'])) {
            return '';
        }
        $stat = $this->view->api()->searchOne('stats', ['entity_name' => $resource['type'], 'entity_id' => $resource['id'], 'type' => Stat::TYPE_RESOURCE])->getContent();
        $userStatus = $this->normalizeUserStatus($userStatus);
        return $this->view->partial('common/statistics-value', [
            'type' => Stat::TYPE_RESOURCE,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Get the stat view for the selected download.
     *
     * @param string|int $downloadId Url or id of the downloaded file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string Html code from the theme.
     */
    public function textDownload($downloadId, ?string $userStatus = null): string
    {
        $userStatus = $this->normalizeUserStatus($userStatus);
        $stat = $this->view->api()->searchOne(
            'stats',
            is_numeric($downloadId)
                ? ['entity_name' => 'media', 'entity_id' => $downloadId, 'type' => Stat::TYPE_DOWNLOAD]
                : ['url' => $downloadId, 'type' => Stat::TYPE_DOWNLOAD]
        )->getContent();
        return $this->view->partial('common/statistics-value', [
            'type' => Stat::TYPE_DOWNLOAD,
            'stat' => $stat,
            'userStatus' => $userStatus,
        ]);
    }

    /**
     * Helper to select the good link maker according to type.
     *
     *@deprecated Useless in Omeka S.
     *
     * @param Resource $resource
     * @return string Html code from the theme.
     */
    public function linkToResource(?AbstractResourceRepresentation $resource): string
    {
        if (empty($resource)) {
            return $this->view->translate('Unavailable'); // @translate
        }
        if (method_exists($resource, 'displayTitle')) {
            $title = $resource->displayTitle();
        } elseif (method_exists($resource, 'title')) {
            $title = $resource->title();
        } elseif (method_exists($resource, 'label')) {
            $title = $resource->label();
        } else {
            $title = '[untitled]'; // @translate
        }
        return $resource->linkRaw($title);
    }

    /**
     * Helper to get the human name of the resource type.
     *
     * @param string $resourceType
     * @param string $defaultEmpty Return this string if empty
     */
    public function humanResourceType($resourceType, ?string $defaultEmpty = null): string
    {
        if (empty($resourceType)) {
            return (string) $defaultEmpty;
        }
        $cleanResourceType = $this->normalizeResourceType($resourceType);
        $translate = $this->view->plugin('translate');
        switch ($cleanResourceType) {
            // Api names
            case 'items':
                return $translate('Item');
            case 'item_sets':
                return $translate('Item set');
            case 'media':
                return $translate('Media');
            case 'resources':
                return $translate('Resource');
            case 'site_pages':
                return $translate('Page');
            default:
                return $translate($resourceType);
        }
    }

    /**
     * Get default user status. This functions is used to allow synonyms.
     *
     * @param string $userStatus
     *
     * @return string
     */
    public function humanUserStatus(?string $userStatus): string
    {
        $translate = $this->view->plugin('translate');
        $userStatus = $this->normalizeUserStatus();
        switch ($userStatus) {
            case 'anonymous':
                return $translate('anonymous users');
            case 'identified':
                return $translate('identified users');
            case 'hits':
            default:
                return $translate('all users');
        }
    }

    /**
     * Clean the url to get better results (remove the domain and base path).
     */
    protected function checkAndCleanUrl(string $url): string
    {
        $plugins = $this->view->getHelperPluginManager();
        $serverUrl = $plugins->get('serverUrl')->__invoke();
        $basePath = $plugins->get('basePath')->__invoke();

        $url = trim($url);

        // Strip out the protocol, host, base URL, and rightmost slash before
        // comparing the URL to the current one
        $stripOut = [$serverUrl . $basePath, @$_SERVER['HTTP_HOST'], $basePath];
        $cleanedUrl = rtrim(str_replace($stripOut, '', $url), '/');

        if (substr($cleanedUrl, 0, 4) === 'http' || substr($cleanedUrl, 0, 1) !== '/') {
            return '';
        }
        return $cleanedUrl;
    }

    /**
     * Check if url is a page one or a download one.
     */
    public function isDownload(string $url): bool
    {
        return (strpos($url, '/files/original/') === 0)
            || (strpos($url, '/files/large/') === 0)
            // For migration from Omeka Classic.
            || (strpos($url, '/files/fullsize/') === 0);
    }

    /**
     * Helper to get params from a resource. If no resource, return empty resource.
     *
     * This allows resource to be an object or an array. This is useful to avoid
     * to fetch a resource when it's not needed, in particular when it's called
     * from the theme.
     *
     * Recommended forms are object and associative array with 'type' (api name)
     * and 'id' as keys.
     *
     * @return array Associative array with resource type and id.
     */
    public function checkAndPrepareResource($resource): array
    {
        if (is_object($resource)) {
            /** @var \Omeka\Api\Representation\AbstractRepresentation $resource */
            $type = $resource->getControllerName();
            $id = $resource->id();
        } elseif (is_array($resource)) {
            if (count($resource) === 1) {
                $id = reset($resource);
                $type = key($resource);
            } elseif (count($resource) === 2) {
                if (is_numeric(key($resource))) {
                    $type = array_shift($resource);
                    $id = array_shift($resource);
                } else {
                    $type = $resource['type'] ?? $resource['resource_type'] ?? $resource['name'] ?? $resource['entity_name'];
                    $id = $resource['id'] ?? $resource['resource_id'] ?? $resource['entity_id'];
                }
            } else {
                return ['type' => '', 'id' => 0];
            }
        } else {
            return ['type' => '', 'id' => 0];
        }
        $type = $this->normalizeResourceType($type);
        return empty($type) || empty($id)
            ? ['type' => '', 'id' => 0]
            : ['type' => $type, 'id' => $id];
    }

    /**
     * Get default user status. This functions is used to allow synonyms.
     */
    protected function normalizeUserStatus(?string $userStatus = null): string
    {
        $userStatuses = [
            'total' => 'hits',
            'hits' => 'hits',
            'anonymous' => 'anonymous',
            'hits_anonymous' => 'anonymous',
            'identified' => 'identified',
            'hits_identified' => 'identified',
        ];
        if (isset($userStatuses[$userStatus])) {
            return $userStatuses[$userStatus];
        }
        return $this->view->status()->isAdminRequest()
            ? (string) $this->view->setting('statistics_default_user_status_admin', 'hits')
            : (string) $this->view->setting('statistics_default_user_status_public', 'anonymous');
    }

    protected function normalizeResourceType(?string $type): ?string
    {
        $apiNames = [
            // Api names
            'items' => 'items',
            'item_sets' => 'item_sets',
            'media' => 'media',
            'resources' => 'resources',
            'site_pages' => 'site_pages',
            // Json-ld names
            'o:Item' => 'items',
            'o:ItemSet' => 'item_sets',
            'o:Media' => 'media',
            'o:SitePage' => 'site_pages',
            // Classes.
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Media::class => 'media',
            \Omeka\Entity\Resource::class => 'resource',
            \Omeka\Entity\SitePage::class => 'site_pages',
            \Omeka\Api\Representation\ItemRepresentation::class => 'items',
            \Omeka\Api\Representation\ItemSetRepresentation::class => 'item_sets',
            \Omeka\Api\Representation\MediaRepresentation::class => 'media',
            \Omeka\Api\Representation\ResourceReference::class => 'resource',
            \Omeka\Api\Representation\SitePageRepresentation::class => 'site_pages',
            // Other names.
            'resource' => 'resources',
            'resource:item' => 'items',
            'resource:itemset' => 'item_sets',
            'resource:media' => 'media',
            // Other resource types or badly written types.
            'o:item' => 'items',
            'o:item_set' => 'item_sets',
            'o:media' => 'media',
            'item' => 'items',
            'item_set' => 'item_sets',
            'item-set' => 'item_sets',
            'itemset' => 'item_sets',
            'resource:item_set' => 'item_sets',
            'resource:item-set' => 'item_sets',
            'page' => 'site_pages',
            'pages' => 'site_pages',
            'site_page' => 'site_pages',
        ];
        return empty($type) ? null : $apiNames[$type] ?? $apiNames[strtolower($type)] ?? null;
    }

    /**
     * Get the current site from the view or the root view (main layout).
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }

    /**
     * Get the current url.
     */
    public function currentUrl(): string
    {
        static $currentUrl;

        if (is_null($currentUrl)) {
            $currentUrl = $this->view->url(null, [], true);
            $basePath = $this->view->basePath();
            if ($basePath && $basePath !== '/') {
                $start = substr($currentUrl, 0, strlen($basePath));
                // Manage specific paths for files.
                if ($start === $basePath) {
                    $currentUrl = substr($currentUrl, strlen($basePath));
                }
            }
        }

        return $currentUrl;
    }
}
