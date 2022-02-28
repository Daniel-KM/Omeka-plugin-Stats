<?php declare(strict_types=1);

namespace Statistics\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Statistics\Api\Representation\StatRepresentation;
use Statistics\Entity\Stat;

/**
 * The Stat table.
 *
 * Get data about stats. May use data from Hit for complex queries.
 *
 * @todo Move some functions into a view helper (or remove them).
 */
class StatAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'type' => 'type',
        'url' => 'url',
        'entity_name' => 'entityName',
        'entity_id' => 'entityId',
        // TODO Clarify query for sort.
        'hits' => 'totalHits',
        'anonymous' => 'totalHitsAnonymous',
        'identified' => 'totalHitsIdentified',
        'hits_anonymous' => 'totalHitsAnonymous',
        'hits_identified' => 'totalHitsIdentified',
        'total_hits' => 'totalHits',
        'total_hits_anonymous' => 'totalHitsAnonymous',
        'total_hits_identified' => 'totalHitsIdentified',
        'hitsAnonymous' => 'totalHitsAnonymous',
        'hitsIdentified' => 'totalHitsIdentified',
        'totalHits' => 'totalHits',
        'totalHitsAnonymous' => 'totalHitsAnonymous',
        'totalHitsIdentified' => 'totalHitsIdentified',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $statusColumns = [
        'hits' => 'hits',
        'anonymous' => 'hits_anonymous',
        'identified' => 'hits_identified',
    ];

    public function getResourceName()
    {
        return 'stats';
    }

    public function getEntityClass()
    {
        return Stat::class;
    }

    public function getRepresentationClass()
    {
        return StatRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['type'])) {
            if (is_array($query['type'])) {
                $qb->andWhere($expr->in(
                    'omeka_root.type',
                    $this->createNamedParameter($qb, $query['type'])
                ));
            } else {
                $qb->andWhere($expr->eq(
                    'omeka_root.type',
                    $this->createNamedParameter($qb, $query['type'])
                ));
            }
        }

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

        // The query may use "resource_type" or "entity_name".
        if (isset($query['resource_type'])) {
            $query['entity_name'] = $query['resource_type'];
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

        // The query may use "resource_id" or "entity_id".
        if (isset($query['resource_id'])) {
            $query['entity_id'] = $query['resource_id'];
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

        if (isset($query['has_resource'])) {
            $query['has_entity'] = $query['has_resource'];
        }
        if (isset($query['has_entity'])) {
            $qb
                ->andWhere(
                    $query['has_entity']
                        ? $expr->neq('omeka_root.entityName', $this->createNamedParameter($qb, ''))
                        : $expr->eq('omeka_root.entityName', $this->createNamedParameter($qb, ''))
                );
        }

        if (isset($query['not_zero']) && is_scalar($query['not_zero'])) {
            // Check the column, because this is the user value.
            $column = $this->statusColumns[$query['not_zero']] ?? 'hits';
            // Here, the columns are classified.
            $classifiedColumns = [
                'hits' => 'totalHits',
                'anonymous' => 'totalHitsAnonymous',
                'identified' => 'totalHitsIdentified',
                'hits_anonymous' => 'totalHitsAnonymous',
                'hits_identified' => 'totalHitsIdentified',
            ];
            $column = $classifiedColumns[$column] ?? 'totalHits';
            $qb->andWhere("omeka_root.$column != 0");
        }

        // TODO For Stat, since/until use the modified date. Add a way to use the created date.

        if (isset($query['since']) && strlen((string) $query['since'])) {
            // Adapted from Omeka classic.
            // Accept an ISO 8601 date, set the tiemzone to the server's default
            // timezone, and format the date to be MySQL timestamp compatible.
            $date = new \DateTime((string) $query['since'], new \DateTimeZone(date_default_timezone_get()));
            // Don't return result when date is badly formatted.
            if (!$date) {
                $qb->andWhere($expr->eq(
                    'omeka_root.modified',
                    $this->createNamedParameter($qb, 'since_error')
                ));
            } else {
                // Select all dates that are greater than the passed date.
                $qb->andWhere($expr->gte(
                    'omeka_root.modified',
                    $this->createNamedParameter($qb, $date->format('Y-m-d H:i:s'))
                ));
            }
        }

        if (isset($query['until']) && strlen((string) $query['until'])) {
            $date = new \DateTime((string) $query['until'], new \DateTimeZone(date_default_timezone_get()));
            // Don't return result when date is badly formatted.
            if (!$date) {
                $qb->andWhere($expr->eq(
                    'omeka_root.modified',
                    $this->createNamedParameter($qb, 'until_error')
                ));
            } else {
                // Select all dates that are lower than the passed date.
                $qb->andWhere($expr->lte(
                    'omeka_root.modified',
                    $this->createNamedParameter($qb, $date->format('Y-m-d H:i:s'))
                ));
            }
        }
    }

    public function sortQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['sort_field']) && is_array($query['sort_field'])) {
            foreach ($query['sort_field'] as $by => $order) {
                parent::sortQuery($qb, [
                    'sort_by' => $by,
                    'sort_order' => $order,
                ]);
            }
        }
        parent::sortQuery($qb, $query);
    }

    public function validateRequest(Request $request, ErrorStore $errorStore): void
    {
        $data = $request->getContent();
        if (empty($data['o:url']) && empty($data['url'])) {
            $errorStore->addError('o:url', 'The stat requires a url.'); // @translate
        }
        if (empty($data['o:type']) && empty($data['type'])) {
            $errorStore->addError('o:url', 'The stat requires a type.'); // @translate
        } else {
            $type = $data['o:type'] ?? $data['type'];
            if (!in_array($type, [Stat::TYPE_PAGE, Stat::TYPE_RESOURCE, Stat::TYPE_DOWNLOAD])) {
                $errorStore->addError('o:url', 'The stat requires a type: "page", "resource", or "download".'); // @translate
            }
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Statistics\Entity\Stat $entity */
        $data = $request->getContent();
        $isUpdate = $request->getOperation() === Request::UPDATE;

        $updatableKeys = [
            'o:total_hits',
            'o:total_hits_anonymous',
            'o:total_hits_identified',
        ];
        $intKeys = $updatableKeys + [
            'o:entity_id',
            'o:user_id',
        ];

        // This is quicker than using inflector.
        $keyMethods = [
            // Since it's a creation, id is set automatically and not updatable.
            // 'o:id' => 'setId',
            'o:type' => 'setType',
            'o:url' => 'setUrl',
            'o:entity_id' => 'setEntityId',
            'o:entity_name' => 'setEntityName',
            'o:total_hits' => 'setTotalHits',
            'o:total_hits_anonymous' => 'setTotalHitsAnonymous',
            'o:total_hits_identified' => 'setTotalHitsIdentified',
            // 'o:created' => 'setCreated',
            // 'o:modified' => 'setModified',
        ];
        foreach ($data as $key => $value) {
            $keyName = substr($key, 0, 2) === 'o:' ? $key : 'o:' . $key;
            if (!isset($keyMethods[$keyName])) {
                continue;
            }
            if ($isUpdate && !in_array($keyName, $updatableKeys)) {
                continue;
            }
            if (in_array($keyName, $intKeys)) {
                $value = (int) $value;
            }
            $method = $keyMethods[$keyName];
            $entity->$method($value);
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        $type = $entity->getType();
        $url = $entity->getUrl();
        if (!in_array($type, [Stat::TYPE_PAGE, Stat::TYPE_RESOURCE, Stat::TYPE_DOWNLOAD])) {
            $errorStore->addError('o:type', 'A stat must have a type ("page", "resource" or "download".'); // @translate
        }
        if (!$url) {
            $errorStore->addError('o:url', 'A stat must have a url.'); // @translate
        } elseif ($type && !$this->isUnique($entity, ['type' => $type, 'url' => $url])) {
            $errorStore->addError('o:url', 'The type should be unique for the url.'); // @translate
        }
    }

    /**
     * Increase total and anonymous/identified hits.
     */
    public function increaseHits(Stat $stat): void
    {
        $stat->setTotalHits($stat->getTotalHits() + 1);
        $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity()
            ? $stat->setTotalHitsIdentified($stat->getTotalHitsIdentified() + 1)
            : $stat->setTotalHitsAnonymous($stat->getTotalHitsAnonymous() + 1);
    }

    /**
     * Total count of hits of the specified page.
     *
     * @uses self::totalHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalPage(?string $url, ?string $userStatus = null): int
    {
        return $this->totalHits([
            'type' => Stat::TYPE_PAGE,
            'url' => (string) $url,
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified resource.
     *
     * @uses self::totalHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResource(?string $entityName, ?int $entityId, ?string $userStatus = null): int
    {
        return $this->totalHits([
            'type' => Stat::TYPE_RESOURCE,
            'entityName' => (string) $entityName,
            'entityId' => (string) $entityId,
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified resource type.
     *
     * @uses self::totalHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResourceType(?string $entityName, $userStatus = null): int
    {
        return $this->totalHits([
            'type' => Stat::TYPE_RESOURCE,
            'entityName' => (string) $entityName,
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified downloaded media file.
     *
     * @uses self::totalHits()
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     * - If numeric, id the downloaded Media.
     * - If string, url or id the downloaded Media.
     * - If Media, total of downloads of this media.
     * @todo Stats of total downloads for item and item set, etc.
     * - If Item, total of dowloaded files of this Item.
     * - If ItemSet, total of downloaded media of all items.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalDownload($value, ?string $userStatus = null): int
    {
        $criteria = $this->normalizeValueForDownload($value);
        return $criteria
            ? $this->totalHits($criteria, $userStatus)
            : 0;
    }

    /**
     * Get the total count of specified hits.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalHits(array $criteria, ?string $userStatus = null): int
    {
        try {
            /** @var Stat $stat */
            $stat = $this->findEntity($criteria);
        } catch (NotFoundException $e) {
            return 0;
        }
        if ($userStatus === 'anonymous') {
            return $stat->getTotalHitsAnonymous();
        }
        return $userStatus === 'identified'
            ? $stat->getTotalHitsIdentified()
            : $stat->getTotalHits();
    }

    /**
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalOfResources(?string $entityName, $userStatus = null): int
    {
        if (!$entityName) {
            return 0;
        }
        $request = new Request(Request::SEARCH, $entityName);
        // Here, it's not possible to check identified user.
        if ($userStatus === 'anonymous') {
            $request->setContent(['is_public' => 1]);
        }
        $entityName = $this->resource->getEntityName();
        return $this->getAdapter($entityName)
            ->search($request)
            ->getTotalResults();
    }

    /**
     * Get the position of a page (first one is the most viewed).
     *
     * @uses self::positionHits()
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionPage(?string $url, ?string $userStatus = null): int
    {
        return $this->positionHits([
            'type' => Stat::TYPE_PAGE,
            'url' => (string) $url,
        ], $userStatus);
    }

    /**
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionResource(?string $entityName, ?int $entityId, ?string $userStatus = null): int
    {
        return $this->positionHits([
            'type' => Stat::TYPE_RESOURCE,
            'entityName' => (string) $entityName,
            'entityId' => (string) $entityId,
        ], $userStatus);
    }

    /**
     * Total count of hits of the specified downloaded media file.
     *
     * @uses self::totalHits()
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     * - If numeric, id the downloaded Media.
     * - If string, url or id the downloaded Media.
     * - If Media, position of this media.
     * @todo Stats of position of downloads for item and item set, etc.
     * - If Item, position of dowloaded files of this Item.
     * - If ItemSet, position of downloaded media of all items.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionDownload($value, ?string $userStatus = null): int
    {
        $criteria = $this->normalizeValueForDownload($value);
        return $criteria
            ? $this->positionHits($criteria, $userStatus)
            : 0;
    }

    /**
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionHits(array $criteria, ?string $userStatus = null): int
    {
        // For security and quick check.
        $criteria = array_filter(array_intersect_key($criteria, [
            'id' => null,
            'type' => null,
            'url' => null,
            'entity_id' => null,
            'entity_name' => null,
            'hits' => null,
            'hits_anonymous' => null,
            'hits_identified' => null,
            'created' => null,
            'modified' => null,
        ]), 'is_null');
        if (empty($criteria)) {
            return 0;
        }

        // This data is not available in stats, so do a direct query.

        /** @var \Doctrine\DBAL\Connection $connection */
        // Don't use the entity manager connection, but the dbal directly
        // (arguments are different).
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $hitsColumn = $this->statusColumns[$userStatus] ?? 'hits';

        // Use two queries to manage complex criteria.
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select("omeka_root.$hitsColumn")
            ->from(\Statistics\Entity\Stat::class, 'omeka_root')
            // Limit by one, but there can't be more than one row when criteria
            // is fine.
            ->limit(1);

        // The query is requested immediatly in order to manage zero viewed
        // resources simply.
        $totalHits = (int) $connection->executeQuery($qb)->fetchOne();
        if (empty($totalHits)) {
            return 0;
        }

        // Build the main query. Sometimes, type and resource type are not set.
        // Default type is "page", since a single type is required to get a good
        // results.
        if (!isset($criteria['type'])) {
            if (isset($criteria['entity_id']) || isset($criteria['entity_name'])) {
                $criteria['type'] = stat::TYPE_RESOURCE;
            } elseif (isset($criteria['url']) && $this->isDownload($criteria['url'])) {
                $criteria['type'] = Stat::TYPE_DOWNLOAD;
            } else {
                $criteria['type'] = Stat::TYPE_PAGE;
            }
        }

        // Simply count the number of position greater than the requested one.
        $qb = $connection->createQueryBuilder()
            ->select('COUNT(DISTINCT(id)) + 1 AS num')
            ->from(\Statistics\Entity\Stat::class, 'omeka_root')
            ->where($expr->gt('omeka_root.' . $hitsColumn, ':total_hits'));

        // $criteria keys are already checked.
        $bind = $criteria;
        $types = [];
        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $qb->andWhere($expr->in("omeka_root.$key", ':key'));
                $types[$key] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            } else {
                $qb->andWhere($expr->eq("omeka_root.$key", ':key'));
            }
        }

        $bind['total_hits'] = $totalHits;
        return (int) $connection->executeQuery($qb, $bind, $types)->fetchOne();
    }

    /**
     * Get the most viewed rows.
     *
     * Filters events are not triggered.
     *
     * @todo Move to an helper.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function vieweds(array $params = [], ?int $limit = null, ?int $page = null): array
    {
        if ($page) {
            $params['page'] = $page;
            if ($limit) {
                $params['per_page'] = $limit;
            }
        } elseif ($limit) {
            $params['limit'] = $limit;
        }

        $request = new Request(Request::SEARCH, 'stats');
        $request
            ->setContent($params)
            ->setOption('initialize', false)
            ->setOption('finalize', false);
        $result = $this->search($request)->getContent();
        foreach ($result as &$stat) {
            $stat = new StatRepresentation($stat, $this);
        }
        return $result;
    }

    /**
     * Get the most viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses self::vieweds().
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     * with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function mostViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_PAGE,
            'has_resource' => $hasResource,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                // This order is needed in order to manage ex-aequos.
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Get the most viewed specified resources.
     *
     * Zero viewed resources are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string|array $entityName If array, may contain multiple
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function mostViewedResources($entityName = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            // Needed if $entityName is empty.
            'type' => Stat::TYPE_RESOURCE,
            'entity_name' => $entityName,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Get the most downloaded files.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function mostViewedDownloads(?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_DOWNLOAD,
            'not_zero' => $column,
            'sort_field' => [
                $column => 'desc',
                'modified' => 'asc',
            ],
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Get the last viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses self::vieweds().
     *
     *@param bool|null $hasResource Null for all pages, true or false to set
     * with or without resource.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function lastViewedPages(?bool $hasResource = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_PAGE,
            'has_resource' => $hasResource,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'asc',
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Get the last viewed specified resources.
     *
     * Zero viewed resources are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string|array $entityName If array, may contain multiple
     * resource types.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function lastViewedResources($entityNames = null, ?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_RESOURCE,
            'entity_name' => $entityNames,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'asc',
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Get the last viewed downloads.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses self::vieweds().
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     * @param int $limit Number of objects to return per "page".
     * @param int $page Page to retrieve.
     * @return StatRepresentation[]
     */
    public function getLastViewedDownloads(?string $userStatus = null, ?int $limit = null, ?int $page = null): array
    {
        $column = $this->statusColumns[$userStatus] ?? 'hits';
        $criteria = [
            'type' => Stat::TYPE_DOWNLOAD,
            'not_zero' => $column,
            'sort_by' => 'modified',
            'sort_order' => 'asc',
        ];
        return $this->vieweds($criteria, $limit, $page);
    }

    /**
     * Normalize a value argument to check downloads.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|string|int $value
     */
    protected function normalizeValueForDownload($value): ?array
    {
        $criteria = ['type' => Stat::TYPE_DOWNLOAD];
        if (is_numeric($value)) {
            $criteria['entity_name'] = 'media';
            $criteria['entity_id'] = (int) $value;
        } elseif (is_string($value)) {
            $criteria['entity_name'] = 'media';
            $criteria['url'] = $value;
        } elseif (is_object($value)) {
            if ($value instanceof \Omeka\Api\Representation\MediaRepresentation) {
                $criteria['entity_name'] = 'media';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Api\Representation\ItemRepresentation) {
                $criteria['entity_name'] = 'items';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Api\Representation\ItemSetRepresentation) {
                $criteria['entity_name'] = 'item_sets';
                $criteria['entity_id'] = $value->id();
            } elseif ($value instanceof \Omeka\Entity\Media) {
                $criteria['entity_name'] = 'media';
                $criteria['entity_id'] = $value->getId();
            } elseif ($value instanceof \Omeka\Entity\Item) {
                $criteria['entity_name'] = 'items';
                $criteria['entity_id'] = $value->getId();
            } elseif ($value instanceof \Omeka\Entity\ItemSet) {
                $criteria['entity_name'] = 'item_sets';
                $criteria['entity_id'] = $value->getId();
            } else {
                // Download are only for media files.
                return null;
            }
            $criteria['entity_id'] = $value->id();
        } else {
            return null;
        }
        return $criteria;
    }

    /**
     * Determine whether or not the hit is from a bot/webcrawler
     *
     * @return bool True if hit is from a bot, otherwise false
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
}
