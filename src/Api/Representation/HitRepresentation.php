<?php declare(strict_types=1);

namespace Stats\Api\Representation;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Stats\Entity\Stat;

class HitRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'hit';
    }

    public function getJsonLd()
    {
        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        return [
            'o:id' => $this->id(),
            'o:url' => $this->hitUrl(),
            'o:entity_id' => $this->entityId(),
            'o:entity_name' => $this->entityName(),
            'o:user_id' => $this->userId(),
            'o:ip' => $this->ip(),
            'o:referrer' => $this->referrer(),
            'o:query' => $this->query(),
            'o:user_agent' => $this->userAgent(),
            'o:accept_language' => $this->acceptLanguage(),
            'o:created' => $created,
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-stats:Hit';
    }

    /**
     * Url is not the full url, but only the Omeka one: no domain, no specific
     * path. So `https://example.org/item/1` is saved as `/item/1` and home
     * page as `/`.
     *
     * Of course, when the files are stored externally, it cannot be an hit here
     * except in case of a specific management (js or redirect).
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
     * Alias of entityName().
     *
     * @see self::entityName().
     */
    public function resourceName(): ?string
    {
        return $this->resource->getEntityName() ?: null;
    }

    /**
     * Alias of entityId().
     *
     * @see self::entityId().
     */
    public function resourceId(): ?string
    {
        return $this->resource->getEntityId() ?: null;
    }

    /**
     * User ID when the page is hit by an identified user.
     */
    public function userId(): ?int
    {
        return $this->resource->getUserId() ?: null;
    }

    /**
     * Remote ip address. It can be obfuscated or protected.
     */
    public function ip(): ?string
    {
        return $this->resource->getIp() ?: null;
    }

    public function referrer(): ?string
    {
        return $this->resource->getReferrer() ?: null;
    }

    public function query(): ?string
    {
        return $this->resource->getQuery() ?: null;
    }

    public function userAgent(): ?string
    {
        return $this->resource->userAgent() ?: null;
    }

    public function acceptLanguage(): ?string
    {
        return $this->resource->acceptLanguage() ?: null;
    }

    /**
     * The date this resource was added.
     */
    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    /**
     * Determine whether or not the page has or had a resource.
     *
     * @return bool True if hit has a resource, even deleted.
     */
    public function hasEntity(): bool
    {
        return $this->resource->getEntityName()
            && $this->resource->getEntityId();
    }

    /**
     * Alias of hasEntity().
     *
     * @see self::hasEntity().
     */
    public function hasResource(): bool
    {
        return $this->resource->getEntityName()
            && $this->resource->getEntityId();
    }

    /**
     * Determine whether or not the hit is from a bot/webcrawler
     */
    public function isBot(): bool
    {
        return $this->adapter->isBot($this->resource->getUserAgent());
    }

    /**
     * Determine whether or not the hit is a direct download.
     *
     * Of course, only files stored locally can be hit.
     * @todo Manage a specific path.
     *
     * @return bool True if hit has a resource, even deleted.
     */
    public function isDownload(): bool
    {
        $url = $this->resource->getUrl();
        return strpos($url, '/files/original/') === 0
            || strpos($url, '/files/large/') === 0
            // For migration from Omeka Classic.
            || strpos($url, '/files/fullsize/') === 0;
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
     * Get the user object if any and not deleted.
     */
    public function user(): ?UserRepresentation
    {
        $id = $this->resource->getUserId() ?: null;
        if (empty($id)) {
            return null;
        }
        try {
            $adapter = $this->getAdapter('users');
            $entity = $adapter->findEntity(['id' => $id]);
            return $adapter->getRepresentation($entity);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Get the stat object.
     *
     * @param string $type "page" or "resource" or "download".
     */
    public function stat(string $type = Stat::TYPE_PAGE): ?StatRepresentation
    {
        switch ($type) {
            case STAT::TYPE_RESOURCE:
                return $this->statResource();
            case STAT::TYPE_DOWNLOAD:
                return $this->statDownload();
            case STAT::TYPE_PAGE:
            default:
                return $this->statPage();
        }
    }

    /**
     * Get the stat object for page. This is the default, so don't check "page".
     */
    public function statPage(): ?StatRepresentation
    {
        // It is useless to store the stat, since it is called one time only in
        // all the real world cases and by doctrine anyway.
        try {
            // This is the default stat, so check url only, not the type.
            $adapter = $this->getAdapter('stats');
            $entity = $adapter->findEntity(['url' => $this->resource->getUrl()]);
            return $adapter->getRepresentation($entity);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Get the stat object of the resource.
     */
    public function statResource(): ?StatRepresentation
    {
        // It is useless to store the stat, since it is called one time only in
        // all the real world cases and by doctrine anyway.
        $name = $this->resource->getEntityName();
        $id = $this->resource->getEntityId();
        if ($name && $id) {
            try {
                $adapter = $this->getAdapter('stats');
                $entity = $adapter->findEntity(['entityName' => $name, 'entityId' => $id]);
                return $adapter->getRepresentation($entity);
            } catch (NotFoundException $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the stat object of the download.
     */
    public function statDownload(): ?StatRepresentation
    {
        // It is useless to store the stat, since it is called one time only in
        // all the real world cases and by doctrine anyway.
        if ($this->isDownload()) {
            try {
                $adapter = $this->getAdapter('stats');
                $entity = $adapter->findEntity([
                    'type' => STAT::TYPE_DOWNLOAD,
                    'url' => $this->resource->getUrl(),
                ]);
                return $adapter->getRepresentation($entity);
            } catch (NotFoundException $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the count of hits of the page (shortcut to stat).
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalPage(?string $userStatus = null): int
    {
        return $this->statPage()->totalPage($userStatus);
    }

    /**
     * Get the count of hits of the resource, if any.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResource(?string $userStatus = null): int
    {
        $stat = $this->statResource();
        return $stat
            ? $stat->totalResource($userStatus)
            : 0;
    }

    /**
     * Get the count of hits of the resource type, if any.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalResourceType(?string $userStatus = null): int
    {
        return $this->getAdapter('stats')->totalResourceType(
            $this->resource->getEntityName(),
            $userStatus
        );
    }

    /**
     * Get the count of hits of the downloaded resource, if any.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function totalDownload(?string $userStatus = null): int
    {
        $stat = $this->statDownload();
        return $stat
            ? $stat->totalDownload($userStatus)
            : 0;
    }

    /**
     * Get the position of the page in the most viewed.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionPage(?string $userStatus = null): int
    {
        return $this->statPage()->positionPage($userStatus);
    }

    /**
     * Get the position of the page in the most viewed.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionResource(?string $userStatus = null): int
    {
        $stat = $this->statResource();
        return $stat
            ? $stat->positionResource($userStatus)
            : 0;
    }

    /**
     * Get the position of the direct download in the most viewed.
     *
     * @param string $userStatus Can be hits (default), anonymous or identified.
     */
    public function positionDownload(?string $userStatus = null): int
    {
        $stat = $this->statDownload();
        return $stat
            ? $stat->positionDownload($userStatus)
            : 0 ;
    }
}
