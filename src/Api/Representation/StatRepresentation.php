<?php

/**
 * Stat synthetises data from Hits.
 *
 * This is a simple cache used to store main stats about a page or a record.
 *
 * @package Stats\models
 */
class Stat extends Omeka_Record_AbstractRecord
{
    /**
     * Three types of stats exists: pages, records and direct downloads.
     * A hit creates or increases values of the stat with the specified url. If
     * this page is dedicated to a record, a second stat is created or increased
     * for the record. If the url is a direct download one, another stat is
     * created or increased.
     * Stats should be created only by Hit (no check is done here).
     *
     * @var string
     */
    public $type = '';

    /**
     * Url is not the full url, but only the Omeka one: no domain, no specific
     * path. So `http://www.example.com/omeka/items/show/1` is saved as
     * `/items/show/1` and home page as `/`. For downloads, url stats with
     * "/files/original/" or "/files/fullsize/".
     *
     * @var string
     */
    public $url = '';

    /**
     * The record type when the page is dedicated to a record.
     *
     * Only one record is saved by hit, the first one, so this should be the
     * dedicated page of a record, for example "/items/show/#".
     *
     * @var string|null
     */
    public $record_type = '';

    /**
     * The record id when the page is dedicated to a record.
     *
     * Only one record is saved by hit, the first one, so this should be the
     * dedicated page of a record, for example "/items/show/#".
     *
     * @var int|null
     */
    public $record_id = 0;

    /**
     * Total hit of this url.
     *
     * @var integer
     */
    public $hits = 0;

    /**
     * Total hit of this url by an anonymous visitor.
     *
     * @var integer
     */
    public $hits_anonymous = 0;

    /**
     * Total hit of this url by an identified user.
     *
     * @var integer
     */
    public $hits_identified = 0;

    /**
     * The date this record was added.
     *
     * @var string
     */
    public $added;

    /**
     * The date this record was added.
     *
     * @var string
     */
    public $modified;

    /**
     * Records related to a stat.
     *
     * @var array
     */
    protected $_related = array(
        'Record' => 'getRecord',
    );

    /**
     * Non-persistent record object. Contains false if not set and null if
     * deleted.
     */
     private $_record = false;

    /**
     * Initialize mixins.
     */
    protected function _initializeMixins()
    {
        // Mysql < 5.6 can't set two current timestamps, so a mixin is added.
        $this->_mixins[] = new Mixin_Timestamp($this);
    }

    /**
     * Determine whether or not the page has or had a Record.
     *
     * @return boolean True if hit has a record, even deleted.
     */
    public function hasRecord()
    {
        return (!empty($this->record_type) && !empty($this->record_id));
    }

    /**
     * Get the record object if any (and not deleted).
     *
     * @return Record|null
     */
    public function getRecord()
    {
        if ($this->_record === false) {
            $this->_record = $this->hasRecord()
                    // Manage the case where record type has been removed.
                    && class_exists($this->record_type)
                ? $this->getTable($this->record_type)->find($this->record_id)
                : null;
        }
        return $this->_record;
    }

    /**
     * Get the specified count of hits of the current type.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotal($userStatus = null)
    {
        switch ($this->type) {
            case 'page': return $this->getTotalPage($userStatus);
            case 'record': return $this->getTotalRecord($userStatus);
            case 'download': return $this->getTotalDownload($userStatus);
        }
    }

    /**
     * Get the specified count of hits of the current page.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalPage($userStatus = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        return $this->$userStatus;
    }

    /**
     * Get the specified count of hits for the current record, if any.
     *
     * The total of records may be different from the total hits in case of
     * multiple urls for the same record.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer|null
     */
    public function getTotalRecord($userStatus = null)
    {
        switch ($this->type) {
            // If type is "record", no sql is needed.
            case 'record':
                $userStatus = $this->_checkUserStatus($userStatus);
                return $this->$userStatus;
            case 'page':
            case 'download':
                if ($this->hasRecord()) {
                    $record = array();
                    $record['record_type'] = $this->record_type;
                    $record['record_id'] = $this->record_id;
                    return $this->getTable('Stat')->getTotalRecord($record, $userStatus);
                }
                break;
        }
    }

    /**
     * Get the specified count of hits for the current record type, if any.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer|null
     */
    public function getTotalRecordType($userStatus = null)
    {
        if ($this->hasRecord()) {
            return $this->getTable('Stat')->getTotalRecordType($this->record_type, $userStatus);
        }
    }

    /**
     * Get the specified count of hits of the current download.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalDownload($userStatus = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        return $this->$userStatus;
    }

    /**
     * Get the specified position of the current type.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPosition($userStatus = null)
    {
        switch ($this->type) {
            case 'page': return $this->getPositionPage($userStatus);
            case 'record': return $this->getPositionRecord($userStatus);
            case 'download': return $this->getPositionDownload($userStatus);
        }
    }

    /**
     * Get the position of the page.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionPage($userStatus = null)
    {
        return $this->getTable('Stat')->getPositionPage($this->url, $userStatus);
    }

    /**
     * Get the position of the record.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionRecord($userStatus = null)
    {
        $record = array();
        $record['record_type'] = $this->record_type;
        $record['record_id'] = $this->record_id;
        return $this->getTable('Stat')->getPositionRecord($record, $userStatus);
    }

    /**
     * Get the position of the download.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionDownload($userStatus = null)
    {
        return $this->getTable('Stat')->getPositionDownload($this->url, $userStatus);
    }

    /**
     * Get the total count of records of the current type.
     *
     * @return integer
     */
    public function getTotalOfRecords()
    {
        if ($this->hasRecord()) {
            return total_records($this->record_type);
        }
    }

    /**
     * Helper to get the human name of the record type.
     *
     * @param string $defaultEmpty Return this string if empty
     *
     * @return string
     */
     public function getHumanRecordType($defaultEmpty = '')
     {
        return get_view()->stats()->human_record_type($this->record_type, $defaultEmpty);
     }

    /**
     * Get a property about the record for display purposes.
     *
     * @param string $property Property to get. Always lowercase.
     * @return mixed
     */
    public function getProperty($property)
    {
        switch($property) {
            case 'record':
                return $this->getRecord();
            case 'hits':
            case 'total':
                return $this->hits;
            case 'identified':
            case 'hits_identified':
                return $this->hits_identified;
            case 'anonymous':
            case 'hits_anonymous':
                return $this->hits_anonymous;
            default:
                return parent::getProperty($property);
        }
    }

    /**
     * Initialize values from a Hit.
     */
     public function setDataFromHit(Hit $hit)
     {
         $this->url = $hit->url;
         $this->record_type = $hit->record_type;
         $this->record_id = $hit->record_id;
     }

    /**
     * Increase total and identified hits.
     */
    public function increaseHits()
    {
        $this->hits++;
        if (current_user()) {
            $this->hits_identified++;
        }
        else {
            $this->hits_anonymous++;
        }
    }

    /**
     * Simple validation.
     */
    protected function _validate()
    {
        if (empty($this->type) || !in_array($this->type, array('page', 'record', 'download'))) {
            $this->addError('type', __('Type should be "page" or "record".'));
        }
    }

    /**
     * Helper to check and get column name for user status ('hits' by default).
     *
     * @internal Recommended user status are 'hits', 'hits_anonymous' and
     * 'hits_identified', but specific methods of this class allow 'anonymous'
     * and 'identified' too. Don't use them in generic functions.
     *
     * @param string $userStatus
     *
     * @return string
     */
    private function _checkUserStatus($userStatus)
    {
        switch($userStatus) {
            case 'anonymous':
            case 'hits_anonymous':
                return 'hits_anonymous';
            case 'identified':
            case 'hits_identified':
                return 'hits_identified';
        }
        return 'hits';
     }
}
