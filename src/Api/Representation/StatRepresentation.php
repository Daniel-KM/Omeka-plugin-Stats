<?php declare(strict_types=1);

namespace Statistics\Api\Representation;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Request;
use Statistics\Entity\Stat;

/**
 * Stat synthetises data from Hits.
 *
 * This is a simple cache used to store main stats about a page or a resource.
 *
 * @todo Move some functions to an helper (don't need to have a stat to get general stats).
 */
class StatRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        // TODO There is no controller for now for stats (or it is Browse).
        return 'stat';
    }

    public function getJsonLd()
    {
        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        $modified = [
            '@value' => $this->getDateTime($this->modified()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        return [
            'o:id' => $this->id(),
            'o:type' => $this->statType(),
            'o:url' => $this->hitUrl(),
            'o:entity_id' => $this->entityId(),
            'o:entity_name' => $this->entityName(),
            'o:total_hits' => $this->totalHits(),
            'o:total_hits_anonymous' => $this->totalHitsAnonymous(),
            'o:total_hits_identified' => $this->totalHitsIdentified(),
            'o:created' => $created,
            'o:modified' => $modified,
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-statistics:Stat';
    }

    /**
     * Three types of stats exists: pages, resources and direct downloads.
     * A hit creates or increases values of the stat with the specified url. If
     * this page is dedicated to a resource, a second stat is created or
     * increased for this resource. If the url is a direct download one, another
     * stat is created or increased.
     *
     * Stats should be created only by Hit (no check is done here).
     */
    public function statType(): string
    {
        return $this->resource->getType();
    }

    /**
     * Url is not the full url, but only the Omeka one: no domain, no specific
     * path. So `https://example.org/item/1` is saved as `/item/1` and home page
     * as `/`. For downloads, url stats with "/files/original/" or "/files/large/"
     * ("/files/fullsize/" for Omeka Classic).
     */
    public function hitUrl(): string
    {
        return $this->resource->getUrl();
    }

    /**
     * The resource type (api name) when the page is dedicated to a resource.
     *
     * Only one resource is saved by hit, the first one, so this should be the
     * dedicated page of a resource , for example "/item/#xxx".
     *
     * The resource may have been removed.
     */
    public function entityName(): ?string
    {
        return $this->resource->getEntityName() ?: null;
    }

    /**
     * The resource id when the page is dedicated to a resource.
     *
     * Only one resource is saved by hit, the first one, so this should be the
     * dedicated page of a resource, for example "/item/#xxx".
     *
     * The resource may have been removed.
     */
    public function entityId(): ?int
    {
        return $this->resource->getEntityId() ?: null;
    }

    /**
     * Total hits of this url.
     */
    public function totalHits(?string $userStatus = null): int
    {
        if ($userStatus === 'anonymous') {
            return $this->resource->getTotalHitsAnonymous();
        } elseif ($userStatus === 'identified') {
            return $this->resource->getTotalHitsIdentified();
        } else {
            return $this->resource->getTotalHits();
        }
    }

    /**
     * Total hits of this url by an anonymous visitor.
     */
    public function totalHitsAnonymous(): int
    {
        return $this->resource->getTotalHitsAnonymous();
    }

    /**
     * Total hits of this url by an identified user.
     */
    public function totalHitsIdentified(): int
    {
        return $this->resource->getTotalHitsIdentified();
    }



    /**
     * The date this resource was added (first hit).
     */
    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    /**
     * The date this resource was updated (last hit).
     */
    public function modified(): \DateTime
    {
        return $this->resource->getModified();
    }

    /**
     * Determine whether or not the page has or had a resource.
     *
     * @return boolean True if hit has a resource, even deleted.
     */
    public function hasResource()
    {
        return $this->resource->getEntityName()
            && $this->resource->getEntityId();
    }

    /**
     * Get the resource object if any and not deleted.
     */
    public function entityResource(): ?AbstractResourceRepresentation
    {
        $name = $this->resource->getEntityName();
        $id = $this->resource->getEntityId();
        if (empty($name) || empty($id)) {
            return null;
        }
        try {
            $adapter = $this->getAdapter($name);
            $entity = $adapter->findEntity(['id' => $id]);
            return $adapter->getRepresentation($entity);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Get the specified count of hits of the current type (page, resource or download).
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function total(?string $userStatus = null): int
    {
        return $this->totalStatType($this->resource->getType(), $userStatus);
    }

    /**
     * Get the specified count of hits of the current page.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalPage(?string $userStatus = null): int
    {
        return $this->totalStatType(STAT::TYPE_PAGE, $userStatus);
    }

    /**
     * Get the specified count of hits for the current resource, if any.
     *
     * The total of resources may be different from the total hits in case of
     * multiple urls for the same resource.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     *
     * @return integer|null
     */
    public function totalResource(?string $userStatus = null): int
    {
        return $this->totalStatType(STAT::TYPE_RESOURCE, $userStatus);
    }

    /**
     * Get the specified count of hits of the current download.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalDownload(?string $userStatus = null): int
    {
        return $this->totalStatType(STAT::TYPE_DOWNLOAD, $userStatus);
    }

    /**
     * Get the specified count of hits for the current resource type, if any.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResourceType(?string $userStatus = null): int
    {
        return $this->adapter->totalResourceType(
            $this->resource->getEntityName(),
            $userStatus
        );
    }

    /**
     * Get the specified count of hits for a type (page, resource or download).
     *
     * @param string $type May be page, resource or download. If not set, use
     * the current type.
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalStatType(?string $type = null, ?string $userStatus = null): int
    {
        $currentType = $this->resource->getType();
        if (!$type || $currentType === $type) {
            if ($userStatus === 'anonymous') {
                return $this->totalHitsAnonymous();
            } elseif ($userStatus === 'identified') {
                return $this->totalHitsIdentified();
            } else {
                return $this->totalHits();
            }
        }
        switch ($type) {
            case STAT::TYPE_RESOURCE:
                return $this->adapter->totalResource(
                    $this->resource->getEntityName(),
                    $this->resource->getEntityId(),
                    $userStatus
                );
            case STAT::TYPE_DOWNLOAD:
                return $this->adapter->totalDownload(
                    $this->resource->getUrl(),
                    $userStatus
                );
            case STAT::TYPE_PAGE:
            default:
                return $this->adapter->totalPage(
                    $this->resource->getUrl(),
                    $userStatus
                );
        }
    }

    /**
     * Get the specified position of the current type.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function position(?string $userStatus = null): int
    {
        $type = $this->resource->getType();
        switch ($type) {
            case STAT::TYPE_RESOURCE:
                return $this->positionResource($userStatus);
            case STAT::TYPE_DOWNLOAD:
                return $this->positionDownload($userStatus);
            case STAT::TYPE_PAGE:
            // Unlike omeka classic, the position is the page when type is unknown.
            default;
                return $this->positionPage($userStatus);
        }
    }

    /**
     * Get the position of the page.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionPage(?string $userStatus = null): int
    {
        return $this->adapter->positionPage(
            $this->resource->getUrl(),
            $userStatus
        );
    }

    /**
     * Get the position of the resource.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionResource(?string $userStatus = null): int
    {
        return $this->adapter->positionResource(
            $this->resource->getEntityName(),
            $this->resource->getEntityId(),
            $userStatus
        );
    }

    /**
     * Get the position of the download.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionDownload(?string $userStatus = null): int
    {
        return $this->adapter->positionDownload(
            $this->resource->getUrl(),
            $userStatus
        );
    }

    /**
     * Get the total count of resources of the current type.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function getTotalOfResources(?string $userStatus = null): int
    {
        return $this->adapter->totalOfResources(
            $this->resource->getEntityName(),
            $userStatus
        );
    }

    /**
     * Helper to get the singular human name of the resource type.
     *
     * @param string $default Return this string if empty, or default if set.
     */
     public function getHumanResourceType(?string $default = null): string
     {
         $types = [
             'items' => 'item',
             'item_sets' => 'item set',
             'media' => 'media',
             'site_pages' => 'site page',
             'annotation' => 'annotation',
             'pages' => 'page',
         ];
         $entityName = $this->resource->getEntityName();
         return $types[$entityName] ?? $default ?? $entityName;
     }
}
