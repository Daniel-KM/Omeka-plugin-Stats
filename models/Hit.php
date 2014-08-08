<?php

/**
 * @package Stats\models
 */
class Hit extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    /**
     * Url is not the full url, but only the Omeka one: no domain, no specific
     * path. So `http://www.example.com/omeka/items/show/1` is saved as
     * `/items/show/1` and home page as `/`.
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
     * User ID when the page is hit by an identified user.
     *
     * @var int
     */
    public $user_id = 0;

    /**
     * Remote ip address. It can be obfuscated or protected.
     *
     * @var string
     */
    public $ip = '';

    /**
     * Useful data from client.
     */
    public $referrer = '';
    public $query = '';
    public $user_agent = '';
    public $accept_language = '';

    /**
     * The date this record was added.
     *
     * @var string
     */
    public $added;

    /**
     * Record related to this hit, if any..
     *
     * @var array
     */
    protected $_related = array(
        'Record' => 'getRecord',
        'User' => 'getUser',
        'StatPage' => 'getStatPage',
        'StatRecord' => 'getStatRecord',
    );

    /**
     * Non-persistent record object. Contains false if not set and null if
     * deleted.
     */
     private $_record = false;

    /**
     * Non-persistent stat object for page.
     */
     private $_stat_page;

    /**
     * Non-persistent stat object for record, if any.
     */
     private $_stat_record;

    /**
     * Non-persistent request object.
     */
     private $_request;


    /**
     * Initialize mixins.
     */
    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Owner($this);
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
        return $this->getStat()->getRecord();
    }

    /**
     * Get the user object if any.
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->user_id) {
            return $this->getTable('User')->find($this->user_id);
        }
    }

    /**
     * Get the stat object.
     *
     * @param string $type "page" or "record".
     *
     * @return Stat
     */
    public function getStat($type = 'page')
    {
        return ($type == 'record')
            ? $this->getStatRecord()
            :  $this->getStatPage();
    }

    /**
     * Get the stat object for page.
     *
     * @return Stat
     */
    public function getStatPage()
    {
        if (empty($this->_stat_page)) {
            $this->_stat_page = $this->getTable('Stat')->findByUrl($this->url);
            // Create a new stat for the case a stat doesn't exist.
            // Hit is counted only when hit is saved.
            if (empty($this->_stat_page)) {
                $this->_stat_page = $this->_setStat('page');
            }
        }
        return $this->_stat_page;
    }

    /**
     * Get the stat object of the record.
     *
     * @return Stat
     */
    public function getStatRecord()
    {
        if ($this->hasRecord()) {
            if (empty($this->_stat_record)) {
                $this->_stat_record = $this->getTable('Stat')->findByRecord(
                    array('record_type' => $this->record_type, 'record_id' => $this->record_id));
                // Create a new stat for the case a stat doesn't exist.
                // Hit is counted only when hit is saved.
                if (empty($this->_stat_record)) {
                    $this->_stat_record = $this->_setStat('record');
                }
            }
            return $this->_stat_record;
        }
    }

    /**
     * Get the count of hits of the page.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalPage($userStatus = null)
    {
        return $this->getStatPage()->getTotalPage($userStatus);
    }

    /**
     * Get the count of hits of the record, if any.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalRecord($userStatus = null)
    {
        return $this->getStatRecord()->getTotalRecord($userStatus);
    }

    /**
     * Get the count of hits of the record type, if any.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getTotalRecordType($userStatus = null)
    {
        return $this->getStatRecord()->getTotalRecordType($userStatus);
    }

    /**
     * Get the position of the page in the most viewed.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionPage($userStatus = null)
    {
        return $this->getStatPage()->getPositionPage($userStatus = null);
    }

    /**
     * Get the position of the page in the most viewed.
     *
     * @param string $userStatus Can be hits (default), hits_anonymous or
     * hits_identified.
     *
     * @return integer
     */
    public function getPositionRecord($userStatus = null)
    {
        return $this->getStatRecord()->getPositionRecord($userStatus = null);
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
            case 'user':
                return $this->getUser();
            case 'stat':
            case 'stat_page':
                return $this->getStatPage();
            case 'stat_record':
                return $this->getStatRecord();
            case 'total':
            case 'total_page':
                return $this->getTotalPage();
            case 'total_record':
                return $this->getTotalRecord();
            case 'record_deleted':
                return $this->hasRecord() ? (boolean) $this->getRecord() : null;
            default:
                return parent::getProperty($property);
        }
    }

    /**
     * Identify Item records as relating to the Items ACL resource.
     *
     * Required by Zend_Acl_Resource_Interface.
     *
     * @return string
     */
    public function getResourceId()
    {
        return 'Hits';
    }

    /**
     * Set record type and record id of the hit.
     *
     * Only one record is saved by hit, the first one, so this should be the
     * dedicated page of a record, for example "/items/show/#".
     *
     * @param Record $record
     */
    public function setRecord($record)
    {
        $this->record_type = get_class($record);
        $this->record_id = $record->id;
    }

    /**
     * Set current viewed page.
     */
    public function setCurrentHit()
    {
        $this->setCurrentRequest();
        $this->setCurrentRecord();
        $this->setCurrentUser();
    }

    /**
     * Set current request.
     */
    public function setCurrentRequest()
    {
        $request = $this->_getRequest();
        $this->setCurrentUrl();
        $this->ip = (string) $this->_getRemoteIP();
        $this->referrer = (string) $request->getServer('HTTP_REFERER');
        $this->query = (string) $request->getServer('QUERY_STRING');
        $this->user_agent = (string) $request->getServer('HTTP_USER_AGENT');
        $this->accept_language = (string) $request->getServer('HTTP_ACCEPT_LANGUAGE');
    }

    /**
     * Set current url (only omeka part).
     */
    public function setCurrentUrl()
    {
        $this->url = $this->_getRequest()->getPathInfo();
    }

    /**
     * Set current record of the hit if any.
     *
     * Only one record is saved by hit, the first one, so this should be the
     * dedicated page of a record, for example "/items/show/#".
     */
    public function setCurrentRecord()
    {
        $params = $this->_getRequest()->getParams();

        // TODO Check if this test is still needed.
        if (!isset($params['module'])) {
            $params['module'] = 'default';
        }

        $records = apply_filters('stats_record', array(), $params);

        $record = reset($records);
        if ($record) {
            $this->setRecord($record);
        }
    }

    /**
     * Set current user.
     *
     * @param Object $request
     */
    public function setCurrentUser()
    {
        $user = current_user();
        $this->user_id = is_object($user) ? $user->id : 0;
    }

    /**
     * Set stat.
     *
     * @param string $type "page" or "record".
     */
    public function _setStat($type = 'page')
    {
        $stat = new Stat;
        $stat->type = ($type == 'record') ? 'record' : 'page';
        $stat->setDataFromHit($this);
        $stat->save();
        return $stat;
    }

    /**
     * Before-save hook.
     *
     * @param array $args
     */
    public function beforeSave($args)
    {
        $this->_cleanUrl();
    }

    /**
     * After-save hook.
     *
     * @param array $args
     */
    public function afterSave($args)
    {
        if ($args['insert']) {
            // Stat is created and filled via getStat() if not exists.
            $stat = $this->getStatPage();
            $stat->increaseHits();
            $stat->save();
            // A second stat is needed to manage record count.
            if ($this->hasRecord()) {
                $stat = $this->getStatRecord();
                $stat->increaseHits();
                $stat->save();
            }
        }
    }

    /**
     * Get remote ip address. This check respects privacy settings.
     *
     * @return string
     */
    protected function _getRemoteIP()
    {
        $privacy = get_option('stats_privacy');
        if ($privacy == 'anonymous') {
            return '';
        }

        // Check if user is behind nginx.
        $server = $this->_getRequest()->getServer();
        $ip = isset($server['HTTP_X_REAL_IP'])
            ? $server['HTTP_X_REAL_IP']
            : $server['REMOTE_ADDR'];

        switch ($privacy) {
            case 'clear': return $ip;
            case 'hashed': return md5($ip);
            case 'partial_3':
                $partial = explode('.', $ip);
                if (isset($partial[3])) {
                    unset($partial[3]);
                }
                return implode('.', $partial);
            case 'partial_2':
                $partial = explode('.', $ip);
                if (isset($partial[3])) {
                    unset($partial[3]);
                    unset($partial[2]);
                }
                return implode('.', $partial);
            case 'partial_3':
                $partial = explode('.', $ip);
                if (isset($partial[3])) {
                    unset($partial[3]);
                    unset($partial[2]);
                    unset($partial[1]);
                }
                return implode('.', $partial);
        }
    }

    /**
     * Helper to check if the url is an Omeka one and to simplify it before
     * save.
     *
     * This is needed when setCurrentUrl() is not used.
     */
    protected function _cleanUrl()
    {
        defined('WEB_RELATIVE') || define('WEB_RELATIVE', parse_url(WEB_ROOT, PHP_URL_PATH));
        // Keep only path to remove domain and query.
        $url = parse_url($this->url, PHP_URL_PATH);
        // Remove relative path if any.
        if (strpos($url, WEB_RELATIVE) === 0) {
            $url = substr($url, strlen(WEB_RELATIVE));
        }
        // Set "/" if empty.
        $this->url = empty($url) ? '/': $url;
    }

    /**
     * Simple validation.
     */
    protected function _validate()
    {
        if (empty($this->url)) {
            $this->addError('url', __('Url is required.'));
        }
    }

    /**
     * Get request.
     *
     * @return Request
     */
    private function _getRequest()
    {
         if (empty($this->_request)) {
            $this->_request = Zend_Controller_Front::getInstance()->getRequest();
         }
         return $this->_request;
    }
}
