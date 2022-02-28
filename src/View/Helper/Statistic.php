<?php
/**
 * Helper to get some public stats.
 *
 * @internal There is no difference between total of page or download, because
 * each url is unique, but there are differences between positions and viewed
 * pages and downloaded files lists.
 */
class Stats_View_Helper_Stats extends Zend_View_Helper_Abstract
{
    protected $_table;

    /**
     * Load the hit table one time only.
     */
    public function __construct()
    {
        $this->_table = get_db()->getTable('Stat');
    }

    /**
     * Get the stats.
     *
     * @return This view helper.
     */
    public function stats()
    {
        return $this;
    }

    /**
     * Hit a new page (to use only with external plugins for not managed urls).
     *
     * No filter is applied to get the eventual record.
     *
     * @param string $url Url
     * @param Record|array $record If array, contains record type and record id.
     */
    public function new_hit($url, $record = null)
    {
        $record = $this->checkAndPrepareRecord($record);
        $hit = new Hit;
        $hit->setCurrentRequest();
        $hit->url = $this->checkAndCleanUrl($url);
        $hit->record_type = $record['record_type'];
        $hit->record_id = $record['record_id'];
        $hit->setCurrentUser();
        $hit->save();
    }

    /**
     * Get the count of hits of the page.
     *
     * @param string $url Url Current url if null.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function total_page($url = null, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        if (is_null($url)) {
            $url = current_url();
        }
        return $this->_table->getTotalPage($url, $userStatus);
    }

    /**
     * Get the count of hits of the record.
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function total_record($record, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        return $this->_table->getTotalRecord($record, $userStatus);
    }

    /**
     * Get the count of hits of the record type.
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function total_record_type($recordType, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        return $this->_table->getTotalRecordType($recordType, $userStatus);
    }

    /**
     * Get the count of hits of a record or sub-record.
     *
     * @param Record|string|integer $value If string or numeric, url or id of the
     * downloaded  file. If Item, returns total of dowloaded files of this Item.
     * If Collection, returns total of downloaded files of all items. If File,
     * returns total of downloads of this file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function total_download($value, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        return $this->_table->getTotalDownload($value, $userStatus);
    }

    /**
     * Get the position of hits of the page.
     *
     * @param string $url Url Current url if null.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function position_page($url = null, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        if (is_null($url)) {
            $url = current_url();
        }
        // Call getPosition() and not getPositionPage() to simplify process of
        // page or download. The check is made later.
        return $this->_table->getPosition(array('url' => $url), $userStatus);
    }

    /**
     * Get the position of hits of the record (by record type).
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function position_record($record, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        return $this->_table->getPositionRecord($record, $userStatus);
    }

    /**
     * Get the position of hits of the download.
     *
     * @todo Position of user is currently unavailable.
     *
     * @param Record|string|integer $value If string or numeric, url or id of the
     * downloaded  file. If Item, returns position of dowloaded files of this
     * Item. If Collection, returns position of downloaded files of all items.
     * If File, returns position of downloads of this file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return integer
     */
    public function position_download($value, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        return $this->_table->getPositionDownload($value, $userStatus);
    }

    /**
     * Get viewed pages.
     *
     *@param null|boolean $hasRecord Null for all pages, boolean to set with or
     * without record.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Offfset to set page to retrieve.
     * @param boolean $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewed_pages($hasRecord = null, $sort = 'most', $userStatus = null, $limit = null, $page = null, $asHtml = true)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        $stats = ($sort == 'last')
            ? $this->_table->getLastViewedPages($hasRecord, $userStatus, $limit, $page)
            : $this->_table->getMostViewedPages($hasRecord, $userStatus, $limit, $page);

        return $asHtml
            ? $this->_viewedHtml($stats, 'page', $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed records.
     *
     * @param Record|array $recordType If array, contains record type.
     * Can be empty, "all", "none", "page" or "download" too.
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Offfset to set page to retrieve.
     * @param boolean $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewed_records($recordType, $sort = 'most', $userStatus = null, $limit = null, $page = null, $asHtml = true)
    {
        // Manage exceptions.
        if (empty($recordType) || $recordType == 'all' || (is_array($recordType) && in_array('all', $recordType))) {
            $recordType = null;
        }
        elseif ($recordType == 'none' || (is_array($recordType) && in_array('none', $recordType))) {
            return $this->viewed_pages(false, $sort, $userStatus, $limit, $page, $asHtml);
        }
        elseif ($recordType == 'page' || (is_array($recordType) && in_array('page', $recordType))) {
            return $this->viewed_pages(null, $sort, $userStatus, $limit, $page, $asHtml);
        }
        elseif ($recordType == 'download' || (is_array($recordType) && in_array('download', $recordType))) {
            return $this->viewed_downloads($sort, $userStatus, $limit, $page, $asHtml);
        }

        $userStatus = $this->_getUserStatus($userStatus);
        $stats = ($sort == 'last')
            ? $this->_table->getLastViewedRecords($recordType, $userStatus, $limit, $page)
            : $this->_table->getMostViewedRecords($recordType, $userStatus, $limit, $page);

        return $asHtml
            ? $this->_viewedHtml($stats, 'record', $sort, $userStatus)
            : $stats;
    }

    /**
     * Get viewed downloads.
     *
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @param integer $limit Number of objects to return per "page".
     * @param integer $page Offfset to set page to retrieve.
     * @param boolean $asHtml Return html (true, default) or array of Stats.
     * @return string|array Return html of array of Stats.
     */
    public function viewed_downloads($sort = 'most', $userStatus = null, $limit = null, $page = null, $asHtml = true)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        $stats = ($sort == 'last')
            ? $this->_table->getLastViewedDownloads($userStatus, $limit, $page)
            : $this->_table->getMostViewedDownloads($userStatus, $limit, $page);

        return $asHtml
            ? $this->_viewedHtml($stats, 'download', $sort, $userStatus)
            : $stats;
    }

    /**
     * Helper to get string from list of stats.
     *
     * @param array $stats Array of stats.
     * @param string $type "page", "record" or "download".
     * @param string $sort Sort by "most" (default) or "last" vieweds.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     * @return string html
     */
    private function _viewedHtml($stats, $type, $sort, $userStatus)
    {
        $html = '';
        if (empty($stats)) {
            $html .= '<div class="stats">' . __('None.') . '</div>';
        }
        else {
            foreach ($stats as $key => $stat) {
                $params = array(
                    'type' => $type,
                    'stat' => $stat,
                    'sort' => $sort,
                    'user_status' => $userStatus,
                    'position' => $key + 1,
                );
                $html .= common('stats-single', $params);
            }
        }

        return $html;
    }

    /**
     * Get the stat view for the selected page.
     *
     * @param string $url Url (current url if empty).
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     *
     * @return string Html code from the theme.
     */
    public function text_page($url = null, $userStatus = null)
    {
       if (empty($url)) {
            $url = current_url();
        }
        $userStatus = $this->_getUserStatus($userStatus);
        $stat = $this->_table->findByUrl($url);
        return common('stats-value', array(
            'type' => 'page',
            'stat' => $stat,
            'user_status' => $userStatus,
        ));
    }

    /**
     * Get the stat view for the selected record.
     *
     * @param Record|array $record If array, contains record type and record id.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     *
     * @return string Html code from the theme.
     */
    public function text_record($record, $userStatus = null)
    {
        // Check and get record.
        $record = $this->checkAndPrepareRecord($record);
        if (empty($record)) {
            return '';
        }
        $userStatus = $this->_getUserStatus($userStatus);
        $stat = $this->_table->findByRecord($record);
        return common('stats-value', array(
            'type' => 'record',
            'stat' => $stat,
            'user_status' => $userStatus,
        ));
    }

    /**
     * Get the stat view for the selected download.
     *
     * @param string|integer $downloadId Url or id of the downloaded file.
     * @param string $userStatus "anonymous" or "identified", else not filtered.
     *
     * @return string Html code from the theme.
     */
    public function text_download($downloadId, $userStatus = null)
    {
        $userStatus = $this->_getUserStatus($userStatus);
        $stat = $this->_table->findByDownload($downloadId);
        return common('stats-value', array(
            'type' => 'download',
            'stat' => $stat,
            'user_status' => $userStatus,
        ));
    }

    /**
     * Helper to select the good link maker according to type.
     *
     * @see link_to()
     *
     * @param Record $record
     *
     * @return string Html code from the theme.
     */
    public function link_to_record($record)
    {
        if (empty($record)) {
            return __('Deleted');
        }
        switch (get_class($record)) {
            case 'Item':
                return link_to_item(null, array(), 'show', $record);
            case 'File':
                return link_to_file_show(array(), null, $record);
            case 'Collection':
                return link_to_collection(null, array(), 'show', $record);
            case 'SimplePagesPage':
                return sprintf('<a href="%s">%s</a>', html_escape(record_url($record)), metadata($record, 'title'));
                break;
            case 'Exhibit':
                return exhibit_builder_link_to_exhibit($record);
            case 'ExhibitPage':
                return exhibit_builder_link_to_exhibit($record->getExhibit(), null, array(), $record);
            default:
                return link_to($record);
        }
    }

    /**
     * Helper to get the human name of the record type.
     *
     * @param string $recordType
     * @param string $defaultEmpty Return this string if empty
     *
     * @return string
     */
    public function human_record_type($recordType, $defaultEmpty = '')
    {
        if (empty($recordType)) {
            return $defaultEmpty;
        }
        switch ($recordType) {
            case 'SimplePagesPage': return __('Simple Page');
            case 'ExhibitPage': return __('Exhibit Page');
        }
        return __($recordType);
    }

    /**
     * Get default user status. This functions is used to allow synonyms.
     *
     * @param string $userStatus
     *
     * @return string
     */
    public function human_user_status($userStatus)
    {
        switch($userStatus) {
            case 'total':
            case 'hits':
                return __('all users');
            case 'anonymous':
            case 'hits_anonymous':
                return __('anonymous users');
            case 'identified':
            case 'hits_identified':
                return __('identified users');
            default:
                return $userStatus;
        }
    }

    /**
     * Clean the url(s) to get better results (remove the domain and the query).
     *
     * @return array|string $url
     */
    public function checkAndCleanUrl($url)
    {
        defined('WEB_RELATIVE') || define('WEB_RELATIVE', parse_url(WEB_ROOT, PHP_URL_PATH));

        if (!is_array($url)) {
            $url = array($url);
        }

        foreach ($url as &$value) {
            // Keep only path to remove domain and query.
            $value = parse_url((string) $value, PHP_URL_PATH);
            // Remove relative path if any.
            if (strpos($value, WEB_RELATIVE) === 0) {
                $value = substr($value, strlen(WEB_RELATIVE));
            }
        }

        $url = array_filter($url);
        if (empty($url)) {
            return '';
        }
        if (count($url) == 1) {
            return reset($url);
        }
        return $url;
    }

    /**
     * Check if url is a page one or a download one.
     *
     * @return boolean
     */
     public function isDownload($url)
     {
        return (strpos($url, '/files/original/') === 0) || (strpos($url, '/files/fullsize/') === 0);
     }

    /**
     * Helper to get params from a record. If no record, return empty record.
     *
     * This allows record to be an object or an array. This is useful to avoid
     * to fetch a record when it's not needed, in particular when it's called
     * from the theme.
     *
     * Recommended forms are object and associative array with 'record_type'
     * and 'record_id' as keys.
     *
     * @return array Associatie array with record type and record id.
     */
    public function checkAndPrepareRecord($record)
    {
        if (is_object($record)) {
            $recordType = get_class($record);
            $recordId = $record->id;
        }
        elseif (is_array($record)) {
            if (isset($record['record_type']) && isset($record['record_id'])) {
                $recordType = $record['record_type'];
                $recordId = $record['record_id'];
            }
            elseif (count($record) == 1) {
                $recordId = reset($record);
                $recordType = key($record);
            }
            elseif (count($record) == 2) {
                $recordType = array_shift($record);
                $recordId = array_shift($record);
            }
            else {
                return array(
                    'record_type' => '',
                    'record_id' => 0,
                );
            }
        }
        else {
            return array(
                'record_type' => '',
                'record_id' => 0,
            );
        }
        return array(
            'record_type' => $recordType,
            'record_id' => $recordId,
        );
    }

    /**
     * Helper to record type.
     *
     * Recommended forms are string or array of strings.
     *
     * @param Record|array|string $recordType Can be one or multiple.
     * @return string|array
     */
    public function checkRecordType($recordType)
    {
        if (is_object($recordType)) {
            return get_class($recordType);
        }
        if (is_array($recordType)) {
            if (isset($recordType['record_type'])) {
                return $recordType['record_type'];
            }
            else {
                foreach ($recordType as &$rt) {
                    if (!is_array($rt)) {
                        $rt = $this->checkRecordType($rt);
                    }
                }
                return $recordType;
            }
        }

        return (string) $recordType;
    }

    /**
     * Get default user status. This functions is used to allow synonyms.
     *
     * @param string $userStatus
     *
     * @return string
     */
    private function _getUserStatus($userStatus = null)
    {
        switch($userStatus) {
            case 'total':
            case 'hits':
                return 'hits';
            case 'anonymous':
            case 'hits_anonymous':
                return 'hits_anonymous';
            case 'identified':
            case 'hits_identified':
                return 'hits_identified';
            default:
                return is_admin_theme()
                    ? get_option('stats_default_user_status_admin')
                    : get_option('stats_default_user_status_public');
        }
    }
}
