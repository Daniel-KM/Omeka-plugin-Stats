<?php declare(strict_types=1);

namespace Statistics\Api\Adapter;

use DateTime;
use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Statistics\Api\Representation\HitRepresentation;
use Statistics\Api\Representation\StatRepresentation;
use Statistics\Entity\Hit;
use Statistics\Entity\Stat;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Entity\AbstractEntity;

/**
 * The Hit table.
 *
 * Get stats about hits. Generally, it's quicker to use the Stat table.
 */
class HitAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'url' => 'url',
        'entity_name' => 'entityName',
        'entity_id' => 'entityId',
        'ip' => 'ip',
        'referrer' => 'referrer',
        'user_agent' => 'userAgent',
        'accept_language' => 'acceptLanguage',
        'created' => 'created',
    ];

    public function getResourceName()
    {
        return 'hits';
    }

    public function getEntityClass()
    {
        return Hit::class;
    }

    public function getRepresentationClass()
    {
        return HitRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['url'])) {
            if (is_array($query['url'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, $query['url'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, $query['url'])
                ));
            }
        }

        if (isset($query['entity_name'])) {
            if (is_array($query['entity_name'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.entityName',
                    $this->createNamedParameter($qb, $query['entity_name'])
                    ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityName',
                    $this->createNamedParameter($qb, $query['entity_name'])
                ));
            }
        }

        if (isset($query['entity_id'])) {
            if (is_array($query['entity_id'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, $query['entity_id'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, $query['entity_id'])
                ));
            }
        }

        if (isset($query['has_entity']) && $query['has_entity'] !== '') {
            if ($query['has_entity']) {
                $qb->andWhere($expr->neq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, '0')
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.entityId',
                    $this->createNamedParameter($qb, '0')
                ));
            }
        }

        if (isset($query['user_id'])) {
            if (is_array($query['user_id'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, $query['user_id'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, $query['user_id'])
                ));
            }
        }

        if (isset($query['user_status'])
            && in_array($query['user_status'], ['identified', 'anonymous'])
        ) {
            if ($query['user_status'] === 'identified') {
                $qb->andWhere($expr->neq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, 0)
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userId',
                    $this->createNamedParameter($qb, 0)
                ));
            }
        }

        if (isset($query['is_download']) && $query['is_download'] !== '') {
            if ($query['is_download']) {
                $qb->andWhere($expr->like(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, '/files/original/%')
                ));
            } else {
                $qb->andWhere($expr->notLike(
                    'omeka_root.url',
                    $this->createNamedParameter($qb, '/files/original/%')
                ));
            }
        }

        if (isset($query['ip'])) {
            if (is_array($query['ip'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.ip',
                    $this->createNamedParameter($qb, $query['ip'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.ip',
                    $this->createNamedParameter($qb, $query['ip'])
                ));
            }
        }

        // TODO There is a special filter for field "referrer": "NOT LIKE ?", WEB_ROOT . '/%'." (it allows to get external referrer only).
        if (isset($query['referrer'])) {
            if (is_array($query['referrer'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.referrer',
                    $this->createNamedParameter($qb, $query['referrer'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.referrer',
                    $this->createNamedParameter($qb, $query['referrer'])
                ));
            }
        }

        if (isset($query['user_agent'])) {
            if (is_array($query['user_agent'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.userAgent',
                    $this->createNamedParameter($qb, $query['user_agent'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.userAgent',
                    $this->createNamedParameter($qb, $query['user_agent'])
                ));
            }
        }

        if (isset($query['accept_language'])) {
            if (is_array($query['accept_language'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.acceptLanguage',
                    $this->createNamedParameter($qb, $query['accept_language'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.acceptLanguage',
                    $this->createNamedParameter($qb, $query['accept_language'])
                ));
            }
        }

        if (isset($query['not_empty'])
            && in_array($query['not_empty'], ['referrer', 'query', 'user_agent', 'accept_language'])
        ) {
            $columns = [
                'referrer' => 'referrer',
                'query' => 'query',
                'user_agent' => 'userAgent',
                'accept_language' => 'acceptLanguage',
            ];
            $qb->andWhere($expr->neq(
                'omeka_root.' . $columns[$query['not_empty']],
                $this->createNamedParameter($qb, '')
            ));
        }
    }

    /**
     * No need to validate: missing data are taken from current request.
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::validateRequest()
     *
     * {@inheritDoc}
     */
    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Statistics\Entity\Hit $entity */
        // A hit cannot be updated here: it's a static resource.
        if (Request::UPDATE === $request->getOperation()) {
            return;
        }

        $data = $request->getContent();
        $data = $this->fillHit($data);

        // This is quicker than using inflector.
        $keyMethods = [
            // Since it is a creation, id is set automatically.
            // 'o:id' => 'setId',
            'o:url' => 'setUrl',
            'o:entity_id' => 'setEntityId',
            'o:entity_name' => 'setEntityName',
            'o:user_id' => 'setUserId',
            'o:ip' => 'setIp',
            'o:referrer' => 'setReferrer',
            'o:query' => 'setQuery',
            'o:user_agent' => 'setUserAgent',
            'o:accept_language' => 'setAcceptLanguage',
            // 'o:created' => 'setCreated',
        ];
        foreach ($data as $key => $value) {
            $keyName = substr($key, 0, 2) === 'o:' ? $key : 'o:' . $key;
            if (!isset($keyMethods[$keyName])) {
                continue;
            }
            $method = $keyMethods[$keyName];
            if (in_array($key, ['o:entity_id', 'o:user_id'])) {
                $value = (int) $value;
            }
            $entity->$method($value);
        }

        $now = new DateTime('now');
        $entity->setCreated($now);

        /** @var \Statistics\Api\Adapter\StatAdapter $statAdapter */
        $statAdapter = $this->getAdapter('stats');
        $entityManger = $this->getEntityManager();

        // Stat is created if not exists.
        // "page" and "download" are mutually exclusive.
        $url = $entity->getUrl();
        $isDownload = $this->isDownload($url);
        $entityName = $entity->getEntityName();
        $entityId = $entity->getEntityId();

        $stat = $this->findStatForHit($entity);
        if ($stat) {
            $stat
                ->setModified($now);
        } else {
            $stat = new Stat();
            $stat
                ->setType($isDownload ? Stat::TYPE_DOWNLOAD : Stat::TYPE_PAGE)
                ->setUrl($url)
                ->setEntityName($entityName)
                ->setEntityId($entityId)
                ->setCreated($now)
                ->setModified($now)
            ;
        }
        $statAdapter->increaseHits($stat);
        $entityManger->persist($stat);

        // A second stat is needed to manage resource count.
        if (!$entityName || !$entityId) {
            return;
        }

        $statResource = $this->findStatForHit($entity, true);
        if ($statResource) {
            $statResource
                ->setModified($now);
        } else {
            $statResource = new Stat();
            $statResource
                ->setType(Stat::TYPE_RESOURCE)
                ->setUrl($url)
                ->setEntityName($entityName)
                ->setEntityId($entityId)
                ->setCreated($now)
                ->setModified($now)
            ;
        }
        $statAdapter->increaseHits($statResource);
        $entityManger->persist($statResource);
    }

    /**
     * Find the matching Stat from a hit, without event and exception.
     */
    public function findStatForHit(Hit $hit, bool $statResource = false): ?Stat
    {
        $url = $hit->getUrl();
        $bind = [
            'url' => $url,
        ];

        $qb = $this->getEntityManager()->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select('omeka_root')
            ->from(Stat::class, 'omeka_root')
            ->where($expr->eq('omeka_root.url', ':url'))
            ->andWhere($expr->eq('omeka_root.type', ':type'))
            ->setMaxResults(1);

        if ($statResource) {
            $entityName = $hit->getEntityName();
            $entityId = $hit->getEntityId();
            if (!$entityName || !$entityId) {
                return null;
            }
            $qb
                ->andWhere($expr->eq('omeka_root.entityName', ':entity_name'))
                ->andWhere($expr->eq('omeka_root.entityId', ':entity_id'));
            $bind['type'] = Stat::TYPE_RESOURCE;
            $bind['entity_name'] = $entityName;
            $bind['entity_id'] = $entityId;
        }

        // Stat is created and filled via getStat() if not exists.
        // "page" and "download" are mutually exclusive.
        elseif ($this->isDownload($url)) {
            $qb
                ->andWhere($expr->eq('omeka_root.entityName', ':entity_name'))
                ->andWhere($expr->eq('omeka_root.entityId', ':entity_id'));
            $bind['type'] = Stat::TYPE_DOWNLOAD;
            $bind['entity_name'] = 'media';
            $bind['entity_id'] = $hit->getEntityId();
        } else {
            $bind['type'] = Stat::TYPE_PAGE;
        }

        return $qb
            ->setParameters($bind)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Fill data with data of the current request.
     *
     * @param array $data
     * @return array
     */
    public function fillHit(array $data = []): array
    {
        // Use "o:" only to manage api.
        $keys = [
            'id' => 'o:id',
            'url' => 'o:url',
            'entity_id' => 'o:entity_id',
            'entity_name' => 'o:entity_name',
            'user_id' => 'o:user_id',
            'ip' => 'o:ip',
            'referrer' => 'o:referrer',
            'query' => 'o:query',
            'user_agent' => 'o:user_agent',
            'accept_language' => 'o:accept_language',
            'created' => 'o:created',
        ];

        $currentEntityNameAndId = $this->currentEntityNameAndId();
        $currentRequest = $this->currentRequest();

        $result = array_fill_keys($keys, null);
        foreach ($keys as $key => $keyName) {
            if (isset($data[$keyName])) {
                $value = $data[$keyName];
            } elseif (isset($data[$key])) {
                $value = $data[$key];
            } else {
                switch ($keyName) {
                    case 'o:id':
                        $value = null;
                        break;
                    case 'o:url':
                        $value = $currentRequest['url'];
                        break;
                    case 'o:entity_id':
                        $value = $currentEntityNameAndId ? $currentEntityNameAndId['id'] : null;
                        break;
                    case 'o:entity_name':
                        $value = $currentEntityNameAndId ? $currentEntityNameAndId['name'] : null;
                        break;
                    case 'o:user_id':
                        $value = $this->currentUser();
                        $value = $value ? $value->getId() : null;
                        break;
                    case 'o:ip':
                        $value = $this->privacyIp();
                        break;
                    case 'o:referrer':
                        $value = $currentRequest['referrer'];
                        break;
                    case 'o:query':
                        $value = $currentRequest['query'];
                        break;
                    case 'o:user_agent':
                        $value = $currentRequest['user_agent'];
                        break;
                    case 'o:accept_language':
                        $value = $currentRequest['accept_language'];
                        break;
                    case 'created':
                        $value = new DateTime('now');
                        break;
                }
            }
            $result[$keyName] = $value;
        }
        return $result;
    }

    protected function currentRequest(): array
    {
        /** @var \Laminas\Mvc\MvcEvent $event */
        $services = $this->getServiceLocator();
        $event = $services->get('Application')->getMvcEvent();
        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $request = $event->getRequest();
        $currentUrl = $request->getRequestUri();

        // Remove the base path, that is useless.
        $basePath = $request->getBasePath();
        if ($basePath && $basePath !== '/') {
            $start = substr($currentUrl, 0, strlen($basePath));
            // Manage specific paths for files.
            if ($start === $basePath) {
                $currentUrl = substr($currentUrl, strlen($basePath));
            }
        }

        $pos = strpos($currentUrl, '?');
        if ($pos !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }

        // Same query via laminas.
        // $query = $request->getUri()->getQuery();
        $query = $_SERVER['QUERY_STRING'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        return [
            'url' => $currentUrl,
            'query' => empty($query) ? null : (string) $query,
            'referrer' => empty($referrer) ? null : (string) $referrer,
            'user_agent' => empty($userAgent) ? null : (string) $userAgent,
            'accept_language' => empty($acceptLanguage) ? null : (string) $acceptLanguage,
        ];
    }

    /**
     * Get the name and the id of the current entity from the route.
     *
     * The filter event "stats_resource" from Omeka classic is useless now.
     *
     * @todo Store the site id and the site page id (it may be slow, so set it during routing?).
     */
    protected function currentEntityNameAndId(): ?array
    {
        /** @var \Laminas\Mvc\MvcEvent $event */
        $event = $this->getServiceLocator()->get('Application')->getMvcEvent();
        $routeParams = $event->getRouteMatch()->getParams();

        $id = $routeParams['id'] ?? $routeParams['resource-id'] ?? $routeParams['media-id'] ?? $routeParams['item-id'] ?? $routeParams['item-set-id'] ?? null;
        if (!$id) {
            return null;
        }

        $name = $routeParams['__CONTROLLER__'] ?? $routeParams['controller'] ?? $routeParams['resource'] ?? null;
        if (!$name) {
            return null;
        }

        // TODO Get the full mapping from controllers to api names.
        $controllerToNames = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'site_page' => 'site_pages',
            'annotation' => 'annotations',
            'Omeka\Controller\Site\Item' => 'items',
            'Omeka\Controller\Site\ItemSet' => 'item_sets',
            'Omeka\Controller\Site\Media' => 'media',
            'Omeka\Controller\Site\Page' => 'site_pages',
            'Annotate\Controller\Site\Annotation' => 'annotations',
        ];

        $name = $controllerToNames[$name] ?? $name . 's';

        // Manage exception for item sets (the item set id is get above).
        if ($name === 'items' && ($routeParams['action'] ?? 'browse') === 'browse') {
            $name = 'item_sets';
        }

        return [
            'name' => $name,
            'id' => $id,
        ];
    }

    protected function currentUser(): ?User
    {
        return $this->getServiceLocator()->get('Omeka\AuthenticationService')
            ->getIdentity();
    }

    /**
     * Determine whether or not the hit is from a bot/webcrawler
     */
    public function isBot(?string $userAgent): bool
    {
        // For dev purpose.
        // print "<!-- UA : " . $this->resource->getUserAgent() . " -->";
        $crawlers = 'bot|crawler|slurp|spider|check_http';
        return $userAgent && preg_match("~$crawlers~", (string) $userAgent);
    }

    /**
     * Determine whether or not the hit is a direct download.
     *
     * Of course, only files stored locally can be hit.
     * @todo Manage a specific path.
     *
     * @return bool True if hit has a resource, even deleted.
     */
    public function isDownload(?string $url): bool
    {
        $url = (string) $url;
        return strpos($url, '/files/original/') === 0
            || strpos($url, '/files/large/') === 0
            // For migration from Omeka Classic.
            || strpos($url, '/files/fullsize/') === 0;
    }

    /**
     * Get the ip of the client.
     *
     * @todo Use the laminas http function.
     */
    public function getClientIp(): string
    {
        // Some servers add the real ip.
        $ip = $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            return $ip;
        }
        return '::';
    }

    /**
     * Manage privacy settings for an ip address.
     *
     * @todo Fix for ipv6.
     */
    public function privacyIp(?string $ip = null): string
    {
        if (is_null($ip)) {
            $ip = $this->getClientIp();
        }

        if (!$ip || $ip === '::') {
            return '::';
        }

        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        switch ($settings->get('statistics_privacy')) {
            default:
            case 'anonymous':
                return '::';
            case 'hashed':
                return md5($ip);
            case 'partial_1':
                $partial = explode('.', $ip);
                $partial[1] = '---';
                $partial[2] = '---';
                $partial[3] = '---';
                return implode('.', $partial);
            case 'partial_2':
                $partial = explode('.', $ip);
                $partial[2] = '---';
                $partial[3] = '---';
                return implode('.', $partial);
            case 'partial_3':
                $partial = explode('.', $ip);
                $partial[3] = '---';
                return implode('.', $partial);
            case 'clear':
                return $ip;
        }
    }

    /**
     * Wrapper for count: get the total count of the specified url.
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param string $url
     *
     * @return integer
     */
    public function getTotal($params)
    {
        return $this->count($params);
    }

    /**
     * Get the total count of the specified url.
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param string $url Url of the page.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalPage($url, $userStatus = null)
    {
        $params = array();
        $params['url'] = $url;
        $params['user_status'] = $userStatus;
        return $this->count($params);
    }

    /**
     * Get the total count of the specified record (or without record).
     *
     * The total of records may be different from the total hits in case of
     * multiple urls for the same record.
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalRecord($record, $userStatus = null)
    {
        $params = array();
        $params['record'] = $record;
        $params['user_status'] = $userStatus;
        return $this->count($params);
    }

    /**
     * Get the total count of the specified record type(s).
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer|null
     */
    public function getTotalRecordType($recordType, $userStatus = null)
    {
        $params = array();
        $params['record_type'] = $recordType;
        $params['user_status'] = $userStatus;
        return $this->count($params);
    }

    /**
     *Get the count of view of a user (or anonymous).
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param integer|User $user User object or user id, or "0" for anonymous.
     *
     * @return integer
     */
    public function getTotalForUser($user)
    {
        $params = array();
        $params['user'] = $user;
        return $this->count($params);
    }

    /**
     *Get the count of view of an IP (the user may be identified or not).
     *
     * Quality of result depends on level of privacy.
     *
     * @uses Omeka_Db_Table::count()
     *
     * @param string $ip
     *
     * @return integer
     */
    public function getTotalForIP($ip)
    {
        $params = array();
        $params['ip'] = $ip;
        return $this->count($params);
    }

    /**
     * Retrieve a count of distinct rows for a field. Empty is not count.
     *
     * @param array $query optional Set of search filters upon which to base
     * the count.
     */
    public function countFrequents(array $query = []): int
    {
        $field = $this->checkFieldForFrequency($query);
        if (!$field) {
            return 0;
        }

        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->currentUser()) {
            $query['is_public'] = 1;
        }

        // Remove empty values.
        $query['not_empty'] = $field;

        $request = new Request(Request::SEARCH, 'hits');
        $request->setContent($query);

        // Begin building the search query.

        $this->index = 0;
        $entityManager = $this->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select("COUNT(DISTINCT(omeka_root.$field))")
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->buildBaseQuery($qb, $query);
        $this->buildQuery($qb, $query);
        // No group here.
        // $qb->groupBy('omeka_root.id');

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        return (int) $qb->getQuery()->getSingleScalarResult();
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
        $field = $this->checkFieldForFrequency($query);
        if (!$field) {
            return [];
        }

        $fieldKey = $this->normalizeFieldForQueryKey($field);

        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->currentUser()) {
            $query['is_public'] = 1;
        }

        // Remove empty values.
        $query['not_empty'] = $field;

        $request = new Request(Request::SEARCH, 'hits');
        $request->setContent($query);

        // Begin building the search query.
        $this->index = 0;
        $entityManager = $this->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select(
                "omeka_root.$field AS $fieldKey",
                "COUNT(omeka_root.$field) AS hits"
            )
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->buildBaseQuery($qb, $query);
        $this->buildQuery($qb, $query);
        // Don't group by id.
        $qb->groupBy("omeka_root.$field");

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        $this->limitQuery($qb, $query);
        $this->sortQuery($qb, $query);
        $qb->addOrderBy('omeka_root.id', $query['sort_order']);

        // Return an array with two columns.
        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Get the most frequent data in a field.
     *
     * @param string $field Name of the column to evaluate.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array Data and total of the according total hits
     */
    public function mostFrequents(string $field, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $query = [];
        $query['field'] = $field;
        $query['user_status'] = $userStatus;
        $query['sort_field'] = array(
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'created' => 'ASC',
        );
        return $this->frequents($query, $limit, $page);
    }

    /**
     * Get the most viewed specified rows with url, resource and total.
     *
     * Zero viewed rows are never returned.
     *
     * Main difference with search() is that values are not resources, but array
     * of synthetic values.
     *
     * @param array $params A set of parameters by which to filter the objects
     *   that get returned from the database.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array of Hits + column total.
     */
    public function vieweds(array $query = [], ?int $limit = null, ?int $page = null): array
    {
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Here, it's not possible to check identified user.
        if (!$this->currentUser()) {
            $query['user_status'] = 'anonymous';
        }

        $request = new Request(Request::SEARCH, 'hits');
        $request->setContent($query);

        // Begin building the search query.

        $this->index = 0;
        $entityManager = $this->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select(
                'omeka_root.url AS url',
                'omeka_root.entity_name AS entity_name' ,
                'omeka_root.entity_id AS entity_id',
                'COUNT(url) AS hits'
                // "@position:=@position+1 AS position"
            )
            ->from(\Statistics\Entity\Hit::class, 'omeka_root');
        $this->buildBaseQuery($qb, $query);
        $this->buildQuery($qb, $query);
        // Don't group by id.
        $qb->groupBy("omeka_root.url");

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        $this->limitQuery($qb, $query);
        $this->sortQuery($qb, $query);
        $qb->addOrderBy('omeka_root.id', $query['sort_order']);

        // Return an array with four columns.
        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Get the most viewed specified pages with url, resource and total.
     *
     * Zero viewed rows are never returned.
     *
     *@param null|bool $hasResource Null for all pages, true or false to set
     *   with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array of Hits + column total.
     */
    public function mostViewedPages($hasResource = null, $userStatus = null, $limit = null, $page = null): array
    {
        $query = [];
        if (!is_null($hasResource)) {
            $query['has_resource'] = (bool) $hasResource;
        }
        $query['user_status'] = $userStatus;
        $query['sort_field'] = [
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'created' => 'ASC',
        ];
        return $this->vieweds($query, $limit, $page);
    }

    /**
     * Get the most viewed specified resources with url, resource and total.
     *
     * Zero viewed resources are never returned.
     *
     * @param string|Resource|array $resourceType If array, may contain multiple
     *   resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array of Hits + column total.
     */
    public function mostViewedResources($entityName = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $query = [];
        $query['entity_name'] = $entityName;
        $query['user_status'] = $userStatus;
        $query['sort_field'] = [
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'created' => 'ASC',
        ];
        return $this->vieweds($query, $limit, $page);
    }

    /**
     * Get the last viewed specified pages with url, resource and total.
     *
     * Zero viewed rows are never returned.
     *
     *@param null|bool $hasResource Null for all pages, true or false to set
     *   with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array of Hits + column total.
     */
    public function lastViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $query = [];
        if (!is_null($hasResource)) {
            $query['has_entity'] = (bool) $hasResource;
        }
        $query['user_status'] = $userStatus;
        $query['sort_by'] = 'created';
        $query['sort_order'] = 'DESC';
        return $this->vieweds($query, $limit, $page);
    }

    /**
     * Get the last viewed specified resources with url, resource and total.
     *
     * Zero viewed resources are never returned.
     *
     * @param string|Resource|array $entityName If array, may contain multiple
     *   resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return array of Hits + column total.
     */
    public function lastViewedResources($entityName = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $query = [];
        $query['entity_name'] = $entityName;
        $query['user_status'] = $userStatus;
        $query['sort_by'] = 'created';
        $query['sort_order'] = 'DESC';
        return $this->vieweds($query, $limit, $page);
    }

    /**
     * Check if there is a key 'field' with a column name for frequency queries.
     */
    protected function checkFieldForFrequency($params): ?string
    {
        $fields = [
            'id' => 'id',
            'url' => 'url',
            'entity_name' => 'entityName',
            'entity_id' => 'entityId',
            'user_id'=> 'userId',
            'ip' => 'ip',
            'query' => 'query',
            'referrer' => 'referrer',
            'user_agent' => 'userAgent',
            'accept_language' => 'acceptLanguage',
            'created' => 'created',
            // For simplicity, but not recommended.
            'entityName' => 'entityName',
            'entityId' => 'entityId',
            'userId'=> 'userId',
            'userAgent' => 'userAgent',
            'acceptLanguage' => 'acceptLanguage',
        ];
        return $fields[$params['field'] ?? null] ?? null;
    }

    /**
     * Check if there is a key 'field' with a column name for frequency queries.
     */
    protected function normalizeFieldForQueryKey(string $field): ?string
    {
        $fields = [
            'entityName' => 'entity_name',
            'entityId' => 'entity_id',
            'userId'=> 'user_id',
            'userAgent' => 'user_agent',
            'acceptLanguage' => 'accept_language',
        ];
        return $fields[$field] ?? $field;
    }
}
