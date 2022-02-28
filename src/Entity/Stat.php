<?php declare(strict_types=1);

namespace Statistics\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * Stat synthetises data from Hits.
 * This is a simple cache used to store main stats about a page or a record.
 * Properties are not nullable to speed up requests.
 *
 * Three types of stats exists: pages, resources and direct downloads.
 * A hit creates or increases values of the stat with the specified url. If this
 * page is dedicated to a resource, a second stat is created or increased for
 * this resource. If the url is a direct download one, another stat is created
 * or increased.
 *
 * @todo Replace the one to two Stats by one (only add columns hits_resource, hits_download (all or identified), so 4 columns (and remove hits_identified).
 *
 * @todo Remove column hits_identified (= hits - hits_anonymous).
 *
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *         @UniqueConstraint(columns={"type", "url"})
 *     },
 *     indexes={
 *         @Index(columns={"type"}),
 *         @Index(columns={"url"}),
 *         @Index(columns={"entity_id"}),
 *         @Index(columns={"entity_name"}),
 *         @Index(columns={"entity_id", "entity_name"}),
 *         @Index(columns={"created"}),
 *         @Index(columns={"modified"})
 *     }
 * )
 */
class Stat extends AbstractEntity
{
    /**#@+
     * Stat types.
     *
     * @todo Remove types and merge stats.
     */
    const TYPE_PAGE = 'page'; // @translate
    const TYPE_RESOURCE = 'resource'; // @translate
    const TYPE_DOWNLOAD = 'download'; // @translate
    /**#@-*/

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
     * @Column(
     *     type="string",
     *     length=8,
     *     nullable=false
     * )
     */
    protected $type;

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
     *     nullable=false,
     *     name="hits"
     * )
     */
    protected $totalHits = 0;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     name="hits_anonymous"
     * )
     */
    protected $totalHitsAnonymous = 0;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     name="hits_identified"
     * )
     */
    protected $totalHitsIdentified = 0;

    /**
     * @var DateTime
     *
     * @Column(
     *      type="datetime",
     *      nullable=false
     * )
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Column(
     *      type="datetime",
     *      nullable=false
     * )
     */
    protected $modified;

    public function getId()
    {
        return $this->id;
    }

    public function setType(?string $type): self
    {
        $this->type = (string) $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
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

    public function setTotalHits(?int $totalHits): self
    {
        $this->totalHits = (int) $totalHits;
        return $this;
    }

    public function getTotalHits(): int
    {
        return $this->totalHits;
    }

    public function setTotalHitsAnonymous(?int $totalHitsAnonymous): self
    {
        $this->totalHitsAnonymous = (int) $totalHitsAnonymous;
        return $this;
    }

    public function getTotalHitsAnonymous(): int
    {
        return $this->totalHitsAnonymous;
    }

    public function setTotalHitsIdentified(?int $totalHitsIdentified): self
    {
        $this->totalHitsIdentified = (int) $totalHitsIdentified;
        return $this;
    }

    public function getTotalHitsIdentified(): int
    {
        return $this->totalHitsIdentified;
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

    public function setModified(DateTime $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTime
    {
        return $this->modified;
    }
}
