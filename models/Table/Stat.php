<?php

/**
 * The Stat table.
 *
 * Get data about stats. May use data from Hit for complex queries.
 *
 * @package Stat\models\Table
 */
class Table_Stat extends Omeka_Db_Table
{
    /**
     * Retrieve the stat for a page.
     *
     * @param string $page Url of the page.
     *
     * @return Stat|ǹull
     */
    public function findByUrl($url)
    {
        $params = array();
        $params['stat_page'] = $url;
        $result = $this->findBy($params, 1);
        return $result ? reset($result) : null;
    }

    /**
     * Retrieve the stat for a record.
     *
     * As multiple stats may be saved because a record can have multiple urls,
     * only the first added one is returned. Stats are always evaluated by
     * direct queries (see Stat class).
     *
     * @param Record|array $record If array, contains record type and record id.
     *
     * @return Stat|null
     */
    public function findByRecord($record)
    {
        $record = get_view()->stats()->checkAndPrepareRecord($record);
        if (empty($record['record_type']) || empty($record['record_id'])) {
            return;
        }

        $params = array();
        $params['stat_record'] = $record;
        $result = $this->findBy($params, 1);
        return $result ? reset($result) : null;
    }

    /**
     * Retrieve the stat for a download.
     *
     * @param string|integer $downloadId Url or id of the downloaded file.
     *
     * @return Stat|null
     */
    public function findByDownload($downloadId)
    {
        $params = array();
        $params['stat_download'] = $downloadId;
        $result = $this->findBy($params, 1);
        return $result ? reset($result) : null;
    }

    /**
     * Get the total count of specified hits.
     *
     * @param array $params Array of params (url, record type, etc.).
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotal($params = array(), $userStatus = null)
    {
        $alias = $this->getTableAlias();
        $userStatus = $this->_checkUserStatus($userStatus);

        $select = $this->getSelectForCount($params)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'hits' => new Zend_Db_Expr("SUM(`$alias`.`$userStatus`)")
            ))
            ->limit(1);

        return $this->getDb()->fetchOne($select);
    }

    /**
     * Wrapper to get the total count of hits of the specified page.
     *
     * @uses Table_Stat::getTotal()
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
        $params['stat_page'] = $url;
        return $this->getTotal($params, $userStatus);
    }

    /**
     * Wrapper to get the total count of hits of the specified record.
     *
     * @uses Table_Stat::getTotal()
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
        $params['stat_record'] = $record;
        return $this->getTotal($params, $userStatus);
    }

    /**
     * Wrapper to get the total count of hits of the specified record type.
     *
     * @uses Table_Stat::getTotal()
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalRecordType($recordType, $userStatus = null)
    {
        $params = array();
        $params['stat_record_type'] = $recordType;
        return $this->getTotal($params, $userStatus);
    }

    /**
     * Wrapper to get the total count of hits of the specified download.
     *
     * @uses Table_Stat::getTotal()
     *
     * @param Record|string|integer $value If string or numeric, url or id the
     * downloaded  file. If Item, returns total of dowloaded files of this Item.
     * If Collection, returns total of downloaded files of all items. If File,
     * returns total of downloads of this file.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalDownload($value, $userStatus = null)
    {
        $params = array();
        if (is_string($value) || is_numeric($value)) {
            $params['stat_download'] = $value;
            return $this->getTotal($params, $userStatus);
        }
        else {
            $params['stat_downloads'] = get_view()->stats()->checkAndPrepareRecord($value);
            return $this->getTotal($params, $userStatus);
        }
    }

    /**
     * Get the position of a stat (first one is the most viewed).
     *
     * @param array $params array of params (url, record type, etc.).
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPosition($params = array(), $userStatus = null)
    {
        if (empty($params)) {
            return 0;
        }

        $alias = $this->getTableAlias();
        $userStatus = $this->_checkUserStatus($userStatus);

        // Build the sub-query to get the hits number for this url or record.
        $subSelect = $this->getSelectForCount($params)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                "$alias.$userStatus",
            ))
            // Limit by one, but there can't be more than one row.
            ->limit(1);

        // The sub-query is requested immediatly in order to manage zero viewed
        // records simply.
        $hits = $this->getDb()->fetchOne($subSelect);
        if (empty($hits)) {
            return 0;
        }

        // Build the main query. Sometimes, type and record type are not set.
        $paramsSelect = array();
        if (isset($params['stat_page'])) {
            $paramsSelect['type'] = 'page';
        }
        elseif (isset($params['stat_download']) || isset($params['stat_downloads'])) {
            $paramsSelect['type'] = 'download';
        }
        elseif (isset($params['stat_record'])) {
            $paramsSelect['stat_record_type'] = $params['stat_record'];
        }
        elseif (isset($params['record'])) {
            $paramsSelect['stat_record_type'] = $params['record'];
        }
        elseif (isset($params['record_type'])) {
            $paramsSelect['stat_record_type'] = $params['record_type'];
        }
        elseif (isset($params['url']) && get_view()->stats()->isDownload($params['url'])) {
            $paramsSelect['type'] = 'download';
        }
        else {
            $paramsSelect['type'] = 'page';
        }
        $select = $this->getSelectForCount($paramsSelect)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'num' => new Zend_Db_Expr('COUNT(*) + 1'),
            ))
            ->where("`$alias`.`$userStatus` > ?", $hits);

        $result = $this->getDb()->fetchOne($select);
        return $result;
    }

    /**
     * Get the position of a page (first one is the most viewed).
     *
     * @uses Table_Stat::getPosition()
     *
     * @param string $url Url of the page.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionPage($url, $userStatus = null)
    {
        $params = array();
        $params['stat_page'] = $url;
        return $this->getPosition($params, $userStatus);
    }

    /**
     * Get the position of a record (first one is the most viewed).
     *
     * @uses Table_Stat::getPosition()
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionRecord($record, $userStatus = null)
    {
        $params = array();
        $params['stat_record'] = $record;
        return $this->getPosition($params, $userStatus);
    }

    /**
     * Wrapper to get the position of the specified download.
     *
     * @uses Table_Stat::getPosition()
     *
     * @param Record|string|integer $value If string or numeric, url or id the
     * downloaded  file. If Item, returns position of dowloaded files of this
     * Item. If Collection, returns position of downloaded files of all items.
     * If File, returns position of downloads of this file.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionDownload($value, $userStatus = null)
    {
        $params = array();
        if (is_string($value) || is_numeric($value)) {
            $params['stat_download'] = $value;
            return $this->getPosition($params, $userStatus);
        }
        else {
            $params['stat_downloads'] = get_view()->stats()->checkAndPrepareRecord($value);
            return $this->getPosition($params, $userStatus);
        }
    }

    /**
     * Get the most viewed rows.
     *
     * Differences with findBy(): no filter and multiple orders are possible.
     *
     * @see Omeka_Db_Table::findBy()
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats
     */
    public function getVieweds(
        $params = array(),
        $limit = null,
        $page = null)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $this->applySearchFilters($select, $params);

        $sortParams = $this->_getSortParams($params);
        if ($sortParams) {
            $this->orderBy($select, $sortParams, array());
        }

        if ($limit) {
            $this->applyPagination($select, $limit, $page);
        }

        return $this->fetchObjects($select);
    }

    /**
     * Get the most viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     *@param null|boolean $hasRecord Null for all pages, true or false to set
     * with or without record.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats
     */
    public function getMostViewedPages($hasRecord = null, $userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        $params['type'] = 'page';
        $params['has_record'] = $hasRecord;
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            $userStatus => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the most viewed specified records.
     *
     * Zero viewed records are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats|null
     */
    public function getMostViewedRecords($recordType = null, $userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        // Needed if $record_type is empty.
        $params['type'] = 'record';
        $params['stat_record_type'] = $recordType;
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            $userStatus => 'DESC',
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the most downloaded files.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats
     */
    public function getMostViewedDownloads($userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        $params['type'] = 'download';
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            $userStatus => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the last viewed pages.
     *
     * Zero viewed pages are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     *@param null|boolean $hasRecord Null for all pages, true or false to set
     * with or without record.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats
     */
    public function getLastViewedPages($hasRecord = null, $userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        $params['type'] = 'page';
        $params['has_record'] = $hasRecord;
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the last viewed specified records.
     *
     * Zero viewed records are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats|null
     */
    public function getLastViewedRecords($recordType = null, $userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        // Needed if $record_type is empty.
        $params['type'] = 'record';
        $params['stat_record_type'] = $recordType;
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the last viewed downloads.
     *
     * Zero viewed downloads are never returned.
     *
     * @uses Table_Stat::getVieweds().
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Stats
     */
    public function getLastViewedDownloads($userStatus = null, $limit = null, $page = null)
    {
        $userStatus = $this->_checkUserStatus($userStatus);
        $params = array();
        $params['type'] = 'download';
        $params['not_zero'] = $userStatus;
        $params['sort_field'] = array(
            'modified' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * @param Omeka_Db_Select
     * @param array
     * @return void
     */
    public function applySearchFilters($select, $params)
    {
        $alias = $this->getTableAlias();
        $boolean = new Omeka_Filter_Boolean;
        $genericParams = array();
        foreach ($params as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) == '')) {
                continue;
            }
            switch ($key) {
                case 'type':
                    $this->filterByType($select, $value);
                    break;
                case 'url':
                    $genericParams['url'] = get_view()->stats()->checkAndCleanUrl($value);
                    break;
                case 'record':
                    $this->filterByRecord($select, $value);
                    break;
                case 'record_type':
                    $genericParams['record_type'] = get_view()->stats()->checkRecordType($value);
                    break;
                case 'has_record':
                    $this->filterByHasRecord($select, $value);
                    break;
                case 'is_download':
                    $this->filterByIsDownload($select, $value);
                    break;
                case 'stat_page':
                    $genericParams['type'] = 'page';
                    $genericParams['url'] = get_view()->stats()->checkAndCleanUrl($value);
                    break;
                case 'stat_record':
                    $genericParams['type'] = 'record';
                    $this->filterByRecord($select, $value);
                    break;
                case 'stat_record_type':
                    $genericParams['type'] = 'record';
                    $genericParams['record_type'] = get_view()->stats()->checkRecordType($value);
                    break;
                case 'stat_download':
                    $genericParams['type'] = 'download';
                    if (is_numeric($value)) {
                        $this->filterByRecord($select, array(
                            'record_type' => 'File',
                            'record_id' => $value,
                        ));
                    }
                    else {
                        $genericParams['url'] = get_view()->stats()->checkAndCleanUrl($value);
                    }
                    break;
                case 'stat_downloads':
                    $this->filterByDownloads($select, $value);
                    break;
                case 'not_zero':
                    $this->filterByNotZero($select, $value);
                    break;
                case 'total':
                    $genericParams['hits'] = $value;
                    break;
                case 'identified':
                    $genericParams['hits_identified'] = $value;
                    break;
                case 'anonymous':
                    $genericParams['hits_anonymous'] = $value;
                    break;
                default:
                    $genericParams[$key] = $value;
            }
        }

        if (!empty($genericParams)) {
            parent::applySearchFilters($select, $genericParams);
        }

        // If we returning the data itself, we need to group by the record id.
        $select->group("$alias.id");
    }

    /**
     * Filter hits by type after check.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param string $type
     * @return void
     */
    public function filterByType($select, $type)
    {
        if (in_array($type, array('page', 'record', 'download'))) {
            $alias = $this->getTableAlias();
            $select->where("`$alias`.`type` = ?", $type);
        }
    }

    /**
     * Filter stats by record (or without record).
     *
     * @param Omeka_Db_Select
     * @param Record|array $record If array, contains record type and record id.
     * @return void
     */
    public function filterByRecord($select, $record)
    {
        $alias = $this->getTableAlias();
        $record = get_view()->stats()->checkAndPrepareRecord($record);
        $select->where("`$alias`.`record_type` = ?", $record['record_type']);
        $select->where("`$alias`.`record_id` = ?", $record['record_id']);
    }

    /**
     * Filter hits that have a record or not.
     *
     * @param Omeka_Db_Select
     * @param null|boolean $hasRecord
     * @return void
     */
    public function filterByHasRecord($select, $hasRecord)
    {
        if (!is_null($hasRecord)) {
            $alias = $this->getTableAlias();
            if ($hasRecord) {
                $select->where("`$alias`.`record_type` != ''");
            }
            else {
                $select->where("`$alias`.`record_type` = ''");
            }
        }
    }

    /**
     * Filter direct download hit.
     *
     * @param Omeka_Db_Select
     * @param null|boolean $isDownload
     * @return void
     */
    public function filterByIsDownload($select, $isDownload)
    {
        if (!is_null($isDownload)) {
            $alias = $this->getTableAlias();
            if ($isDownload) {
                $select->where("`$alias`.`type` = 'download'");
            }
            else {
                $select->where("`$alias`.`type` != 'download'");
            }
        }
    }

    /**
     * Filter direct download hit by group hits on files for Item or Collection.
     *
     * @param Omeka_Db_Select
     * @param Record|array $record If array, contains record type and record id.
     * @return void
     */
    public function filterByDownloads($select, $record)
    {
        $alias = $this->getTableAlias();
        $this->filterByType($select, 'download');
        $record = get_view()->stats()->checkAndPrepareRecord($record);
        $select->where("`$alias`.`record_type` = 'File'");
        switch($record['record_type']) {
            case 'Item':
                $select->joinInner(
                    array('files' => $this->getDb()->File),
                    "stats.record_type = 'File' AND stats.record_id = files.id",
                    array());
                $select->where("`files`.`item_id` = ?", $record['record_id']);
                break;
            case 'Collection':
                $select->joinInner(
                    array('files' => $this->getDb()->File),
                    "stats.record_type = 'File' AND stats.record_id = files.id",
                    array());
                $select->joinInner(
                    array('items' => $this->getDb()->Item),
                    'files.item_id = items.id',
                    array());
                $select->where("`items`.`collection_id` = ?", $record['record_id']);
                break;
            case 'File':
                $select->where("`$alias`.`record_id` = ?", $record['record_id']);
                break;
        }
    }

    /**
     * Filter hits where the value of the specified column is greater than 0.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param string $column The name of the column to evaluate
     * @return void
     */
    public function filterByNotZero($select, $column)
    {
        $alias = $this->getTableAlias();
        // Check the column, because this is the user value.
        $columns = $this->getColumns();
        if (in_array($column, $columns)) {
            $select->where("`$alias`.`$column` != 0");
        }
    }

    /**
     * Get and parse sorting parameters (may be multiples).
     *
     * A sorting direction of 'ASC' will be used if no direction parameter is
     * passed.
     *
     * @see Omeka_Db_Table::_getSortParams()
     *
     * @param array $params Sort field may be an array of fields as key and
     * direction as value.
     * @return array|null Array of sort field and sort dir if params exist, null
     * otherwise.
     */
    private function _getSortParams($params)
    {
        if (!isset($params[self::SORT_PARAM]) || empty($params[self::SORT_PARAM])) {
            return;
        }
        $sortField = $params[self::SORT_PARAM];

        // Order by multiple fields.
        if (is_array($sortField)) {
            foreach ($sortField as &$field) {
                if (in_array($field, array('total', 'anonymous', 'identified'))) {
                    $field = $this->_checkUserStatus($field);
                }
            }
            return $sortField;
        }

        // Order by one field.
        // Don't forget to check specific user status.
        if (in_array($sortField, array('total', 'anonymous', 'identified'))) {
            $sortField = $this->_checkUserStatus($sortField);
        }

        $sortDir = isset($params[self::SORT_DIR_PARAM])
                && in_array($params[self::SORT_DIR_PARAM], array('d', 'DESC'))
            ? 'DESC'
            : 'ASC';
        return array($sortField => $sortDir);
    }

    /**
     * Order select.
     *
     * @param Omeka_Db_Select
     * @param array $order Associative array of column name and direction.
     * @param array $aliasColumns Allows alias of columns and not only their
     * names.
     * @return void
     */
     public function orderBy($select, $order, $aliasColumns = array())
     {
        $alias = $this->getTableAlias();
        $columns = $this->getColumns();
        foreach ($order as $sortField => $sortDir) {
            if (in_array($sortField, $columns)) {
                $sortDir = in_array($sortDir, array('DESC', 'd')) ? 'DESC' : 'ASC';
                $select->order(array("$alias.$sortField $sortDir"));
            }
            elseif (in_array($sortField, $aliasColumns)) {
                $sortDir = in_array($sortDir, array('DESC', 'd')) ? 'DESC' : 'ASC';
                $select->order(array("$sortField $sortDir"));
            }
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
