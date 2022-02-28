<?php declare(strict_types=1);

namespace Stats\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * Resources are not linked to other tables to be kept when source is deleted,
 * but they are indexed. Furthermore, there can be multiple stats for each hit,
 * according to type (page, resource, download).
 *
 * Properties are not nullable to speed up requests.
 *
 * @todo Check if full separation of the two tables is still needed with doctrine (performance). Check if indexes are needed too.
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(columns={"url"}),
 *         @Index(columns={"entity_id"}),
 *         @Index(columns={"entity_name"}),
 *         @Index(columns={"entity_id", "entity_name"}),
 *         @Index(columns={"user_id"}),
 *         @Index(columns={"ip"}),
 *         @Index(columns={"referrer"}),
 *         @Index(columns={"user_agent"}),
 *         @Index(columns={"accept_language"}),
 *         @Index(columns={"created"})
 *     }
 * )
 */
class Hit extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var string
     *
     * In Omeka S, a url may be very long: site name, page name, file name, etc.
     * Furthermore, some identifiers are case sensitive. And they need to be
     * indexed. So the choice of the length and the collation.
     *
     * @Column(
     *     type="string",
     *     length=1024,
     *     nullable=false,
     *     options={
     *         "collation": "latin1_bin"
     *     }
     * )
     */
    protected $url;

    /**
     * API resource id (not necessarily an Omeka main Resource).
     *
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $entityId = 0;

    /**
     * API resource name (not necessarily an Omeka main Resource).
     *
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=false
     * )
     */
    protected $entityName = '';

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $userId = 0;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=45,
     *     nullable=false
     * )
     */
    protected $ip = '';

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $query = '';

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=1024,
     *     nullable=false
     * )
     */
    protected $referrer = '';

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=1024,
     *     nullable=false
     * )
     */
    protected $userAgent = '';

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=false
     * )
     */
    protected $acceptLanguage = '';

    /**
     * @var DateTime
     *
     * @Column(
     *      type="datetime",
     *      nullable=false
     * )
     */
    protected $created;

    public function getId()
    {
        return $this->id;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = (int) $entityId;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityName(?string $entityName): self
    {
        $this->entityName = (string) $entityName;
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = (int) $userId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = (string) $ip;
        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setQuery(?string $query): self
    {
        $this->query = (string) $query;
        return $this;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setReferrer(?string $referrer): self
    {
        $this->referrer = (string) $referrer;
        return $this;
    }

    public function getReferrer(): string
    {
        return $this->referrer;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = (string) $userAgent;
        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setAcceptLanguage(?string $acceptLanguage): self
    {
        $this->acceptLanguage = (string) $acceptLanguage;
        return $this;
    }

    public function getAcceptLanguage(): string
    {
        return $this->acceptLanguage;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }
}
