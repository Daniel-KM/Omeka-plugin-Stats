<?php

/**
 * The Hit table.
 *
 * Get stats about hits. Generally, it's quicker to use the Stat table.
 *
 * @package Hit\models\Table
 */
class Table_Hit extends Omeka_Db_Table
{
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
     * @uses Omeka_Db_Table::getSelectForCount()
     * @param array $params optional Set of search filters upon which to base
     * the count.
     *
     * @return integer
     */
    public function countFrequents($params = array())
    {
        $field = $this->_checkHasFieldForFrequency($params);
        if (!$field) {
            return;
        }

        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $this->applySearchFilters($select, $params);

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'hits' => new Zend_Db_Expr("COUNT(DISTINCT(`$alias`.`$field`))"),
            ))
            ->reset(Zend_Db_Select::GROUP);

        // Remove empty values.
        $this->filterByNotEmpty($select, $field);

        return $this->_db->fetchOne($select);
    }

    /**
     * Get the most frequent data in a field. Empty values are never returned.
     *
     * @internal Main difference with findBy() are that values are not
     * records, but array of synthetic values.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database. This should contains a 'field' for
     * the name of the column to evaluate.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array Data and total hits.
     */
    public function getFrequents($params, $limit = null, $page = null)
    {
        $field = $this->_checkHasFieldForFrequency($params);
        if (!$field) {
            return;
        }

        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $this->applySearchFilters($select, $params);

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                "$alias.$field",
                'hits' => new Zend_Db_Expr('COUNT(*)'),
            ))
            ->reset(Zend_Db_Select::GROUP)
            ->group("$alias.$field");

        // Remove empty values.
        $this->filterByNotEmpty($select, $field);

        $sortParams = $this->_getSortParams($params);
        if ($sortParams) {
            $this->orderBy($select, $sortParams, array('hits'));
        }

        if ($limit) {
            $this->applyPagination($select, $limit, $page);
        }

        // Return an array with two columns.
        $result = $this->_db->query($select, array())->fetchAll();
        return $result;
    }

    /**
     * Get the most frequent data in a field.
     *
     * @uses Table_Hit::getFrequents()
     *
     * @param string $field Name of the column to evaluate.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array Data and total of the according total hits
     */
    public function getMostFrequents($field, $userStatus = null, $limit = null, $page = null)
    {
        $params = array();
        $params['field'] = $field;
        $params['user_status'] = $userStatus;
        $params['sort_field'] = array(
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'added' => 'ASC',
        );
        return $this->getFrequents($params, $limit, $page);
    }

    /**
     * Check if there is a key 'field' with a column name for frequency queries.
     *
     * @param array $params
     * @return void
     */
     protected function _checkHasFieldForFrequency($params)
    {
        if (!isset($params['field']) || !in_array($params['field'], $this->getColumns())) {
            return;
        }
        return $params['field'];
    }

    /**
     * Get the most viewed specified rows with url, record and total.
     *
     * Zero viewed rows are never returned.
     *
     * @internal Main difference with findBy() are that values are not
     * records, but array of synthetic values.
     *
     * @param array $params A set of parameters by which to filter the objects
     * that get returned from the database.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Hits + column total.
     */
    public function getVieweds(
        $params = array(),
        $limit = null,
        $page = null)
    {
        $alias = $this->getTableAlias();
        $select = $this->getSelect();
        $this->applySearchFilters($select, $params);

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'url' => "$alias.url",
                'record_type' => "$alias.record_type",
                'record_id' => "$alias.record_id",
                'hits' => new Zend_Db_Expr('COUNT(*)'),
                // "@position:=@position+1 AS position",
            ))
            ->reset(Zend_Db_Select::GROUP)
            ->group("$alias.url");

        $sortParams = $this->_getSortParams($params);
        if ($sortParams) {
            $this->orderBy($select, $sortParams, array('hits'));
        }

        if ($limit) {
            $this->applyPagination($select, $limit, $page);
        }

        // Return an array with four columns.
        $result = $this->_db->query($select, array())->fetchAll();
        return $result;
    }

    /**
     * Get the most viewed specified pages with url, record and total.
     *
     * Zero viewed rows are never returned.
     *
     * @uses Table_Hit::getVieweds().
     *
     *@param null|boolean $hasRecord Null for all pages, true or false to set
     * with or without record.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Hits + column total.
     */
    public function getMostViewedPages($hasRecord = null, $userStatus = null, $limit = null, $page = null)
    {
        $params = array();
        $params['has_record'] = $hasRecord;
        $params['user_status'] = $userStatus;
        $params['sort_field'] = array(
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'added' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the most viewed specified records with url, record and total.
     *
     * Zero viewed records are never returned.
     *
     * @uses Table_Hit::getVieweds().
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Hits + column total.
     */
    public function getMostViewedRecords($recordType = null, $userStatus = null, $limit = null, $page = null)
    {
        $params = array();
        $params['record_type'] = $recordType;
        $params['user_status'] = $userStatus;
        $params['sort_field'] = array(
            'hits' => 'DESC',
            // This order is needed in order to manage ex-aequos.
            'added' => 'ASC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the last viewed specified pages with url, record and total.
     *
     * Zero viewed rows are never returned.
     *
     * @uses Table_Hit::getVieweds().
     *
     *@param null|boolean $hasRecord Null for all pages, true or false to set
     * with or without record.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Hits + column total.
     */
    public function getLastViewedPages($hasRecord = null, $userStatus = null, $limit = null, $page = null)
    {
        $params = array();
        $params['has_record'] = (boolean) $hasRecord;
        $params['user_status'] = $userStatus;
        $params['sort_field'] = array(
            'added' => 'DESC',
        );
        return $this->getVieweds($params, $limit, $page);
    }

    /**
     * Get the last viewed specified records with url, record and total.
     *
     * Zero viewed records are never returned.
     *
     * @uses Table_Hit::getVieweds().
     *
     * @param string|Record|array $recordType If array, may contain multiple
     * record types.
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Page to retrieve.
     *
     * @return array of Hits + column total.
     */
    public function getLastViewedRecords($recordType = null, $userStatus = null, $limit = null, $page = null)
    {
        $params = array();
        $params['record_type'] = $recordType;
        $params['user_status'] = $userStatus;
        $params['sort_field'] = array(
            'added' => 'DESC',
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
                case 'user':
                    $user_id = $this->_getUserId($value);
                    $this->filterByUser($select, $user_id, 'user_id');
                    break;
                case 'user_status':
                    $this->filterByUserStatus($select, $value);
                    break;
                case 'since':
                    $this->filterBySince($select, $value, 'added');
                    break;
                case 'until':
                    $this->filterByUntil($select, $value, 'added');
                    break;
                case 'field':
                    $this->filterByField($select, $value);
                    break;
                case 'not_empty':
                    $this->filterByNotEmpty($select, $value);
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
     * Filter hits by record (or without record).
     *
     * @see self::applySearchFilters()
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
                $select->where("`$alias`.`url` LIKE '/files/original/%'");
                $select->where("`$alias`.`url` LIKE '/files/fullsize/%'");
            }
            else {
                $select->where("`$alias`.`url` NOT LIKE '/files/original/%'");
                $select->where("`$alias`.`url` NOT LIKE '/files/fullsize/%'");
            }
        }
    }

    /**
     * Filter hits by status of user (anonymous or identified).
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param string $userStatus "hits_anonymous" or "hits_identified", else not filtered.
     * @return void
     */
    public function filterByUserStatus($select, $userStatus)
    {
        $alias = $this->getTableAlias();
        switch ($userStatus) {
            case 'anonymous':
            case 'hits_anonymous':
                $select->where("`$alias`.`user_id` = 0");
                break;
            case 'identified':
            case 'hits_identified':
                $select->where("`$alias`.`user_id` > 0");
                break;
            // default: no filter.
        }
    }

    /**
     * Filter select object by date until.
     *
     * @param Zend_Db_Select $select
     * @param string $dateSince ISO 8601 formatted date (now if empty)
     * @param string $dateField "added" or "modified"
     */
    public function filterByUntil($select, $dateUntil, $dateField)
    {
        // Reject invalid date fields.
        if (!in_array($dateField, array('added', 'modified'))) {
            return;
        }

        // Accept an ISO 8601 date, set the tiemzone to the server's default
        // timezone, and format the date to be MySQL timestamp compatible.
        $date = new Zend_Date($dateUntil, Zend_Date::ISO_8601);
        $date->setTimezone(date_default_timezone_get());
        $date = $date->get('yyyy-MM-dd HH:mm:ss');

        // Select all dates that are greater than the passed date.
        $alias = $this->getTableAlias();
        $select->where("`$alias`.`$dateField` <= ?", $date);
    }

    /**
     * Filter hits by field and remove empty ones.
     *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param string $column The name of the column to evaluate
     * @return void
     */
    public function filterByNotEmpty($select, $column)
    {
        $columns = $this->getColumns();
        if (in_array($column, $columns)) {
            $alias = $this->getTableAlias();
            $select->where("`$alias`.`$column` != ''");
        }
    }

   /**
     * Manage special fields for specific columns.
    *
     * @see self::applySearchFilters()
     * @param Omeka_Db_Select
     * @param string $field The name of the column to evaluate
     * @return void
     */
    public function filterByField($select, $field)
    {
        $alias = $this->getTableAlias();
        switch ($field) {
            case 'referrer':
                $select->where("`$alias`.`referrer` NOT LIKE ?", WEB_ROOT . '/%');
                break;
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

    /**
     * Helper to get id from a user (can be an User object or an id).
     *
     * @param User|integer $user
     * @return integer|null.
     */
    protected function _getUserId($user)
    {
        return (is_object($user) && $user instanceof User)
            ? $user->id
            : (integer) $user;
    }
}
