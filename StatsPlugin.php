<?php
/**
 * Stats
 *
 * Logger that counts views of pages and records and makes stats about usage and
 * users of the site.
 *
 * @copyright Copyright Daniel Berthereau, 2014
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package Stats
 */

/**
 * The Stats plugin.
 *
 * @package Omeka\Plugins\Stats
 */
class StatsPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'initialize',
        'install',
        'upgrade',
        'uninstall',
        'config_form',
        'config',
        'define_acl',
        'define_routes',
        'public_head',
        'admin_items_show_sidebar',
        'admin_collections_show_sidebar',
        'admin_files_show_sidebar',
        'admin_items_browse_simple_each',
        'admin_items_browse_detailed_each',
        'public_items_show',
        'public_items_browse_each',
        'public_collections_show',
        'public_collections_browse_each',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'public_navigation_main',
        'admin_navigation_global',
        'admin_dashboard_stats',
        'admin_dashboard_panels',
        'stats_record',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        // Without roles.
        'stats_public_allow_summary' => false,
        'stats_public_allow_browse_pages' => false,
        'stats_public_allow_browse_records' => false,
        'stats_public_allow_browse_downloads' => false,
        'stats_public_allow_browse_fields' => false,
        // With roles, in particular if Guest User is installed.
        'stats_roles_summary' => 'a:1:{i:0;s:5:"admin";}',
        'stats_roles_browse_pages' => 'a:1:{i:0;s:5:"admin";}',
        'stats_roles_browse_records' => 'a:1:{i:0;s:5:"admin";}',
        'stats_roles_browse_downloads' => 'a:1:{i:0;s:5:"admin";}',
        'stats_roles_browse_fields' => 'a:1:{i:0;s:5:"admin";}',
        // Display.
        'stats_default_user_status_admin' => 'hits',
        'stats_default_user_status_public' => 'hits_anonymous',
        'stats_per_page_admin' => 100,
        'stats_per_page_public' => 10,
        'stats_display_by_hooks' => 'a:10:{i:0;s:15:"admin_dashboard";i:1;s:24:"admin_items_show_sidebar";i:2;s:30:"admin_collections_show_sidebar";i:3;s:24:"admin_files_show_sidebar";i:4;s:30:"admin_items_browse_simple_each";i:5;s:32:"admin_items_browse_detailed_each";i:6;s:17:"public_items_show";i:7;s:24:"public_items_browse_each";i:8;s:23:"public_collections_show";i:9;s:30:"public_collections_browse_each";}',
        // Privacy settings.
        'stats_privacy' => 'hashed',
        'stats_excludebots' => 0
    );

    /**
     * Add the translations.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
        if (version_compare(OMEKA_VERSION, '2.2', '>=')) {
            add_shortcode('stats_total', array($this, 'shortcodeStatsTotal'));
            add_shortcode('stats_position', array($this, 'shortcodeStatsPosition'));
            add_shortcode('stats_vieweds', array($this, 'shortcodeStatsVieweds'));
        }
    }

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        // This two tables are not linked in order to keep the schema KISS.
        // Many indexes are needed to get stats quickly.
        $db = $this->_db;

        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->Stat` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `type` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
            `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `record_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `record_id` int(10) unsigned NOT NULL DEFAULT 0,
            `hits` int(10) unsigned  NOT NULL DEFAULT 0,
            `hits_anonymous` int(10) unsigned NOT NULL DEFAULT 0,
            `hits_identified` int(10) unsigned NOT NULL DEFAULT 0,
            `added` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
            `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `type` (`type`),
            INDEX `url` (`url`),
            INDEX `record_type` (`record_type`),
            INDEX `record_type_record_id` (`record_type`, `record_id`),
            INDEX `hits` (`hits`),
            INDEX `hits_anonymous` (`hits_anonymous`),
            INDEX `hits_identiied` (`hits_identified`),
            INDEX `modified` (`modified`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->Hit` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `record_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `record_id` int(10) unsigned NOT NULL DEFAULT 0,
            `user_id` int(10) NOT NULL DEFAULT 0,
            `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `referrer` varchar(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `query` varchar(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `user_agent` varchar(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `accept_language` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `url` (`url`),
            INDEX `record_type` (`record_type`),
            INDEX `record_type_record_id` (`record_type`, `record_id`),
            INDEX `user_id` (`user_id`),
            INDEX `ip` (`ip`),
            INDEX `referrer` (`referrer`),
            INDEX `query` (`query`),
            INDEX `user_agent` (`user_agent`),
            INDEX `accept_language` (`accept_language`),
            INDEX `added` (`added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        $this->_installOptions();
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        if (version_compare($oldVersion, '2.2.1', '<')) {
            $sql = "
                ALTER TABLE `{$db->Stat}`
                ALTER `added` SET DEFAULT '2000-01-01 00:00:00'
            ";
            $db->query($sql);
        }
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `$db->Stat`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `$db->Hit`";
        $db->query($sql);

        $this->_uninstallOptions();
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        // Default hooks in Omeka Core.
        $displayByHooks = array(
            'admin_dashboard',
            'admin_items_show_sidebar',
            'admin_collections_show_sidebar',
            'admin_files_show_sidebar',
            'admin_items_browse_simple_each',
            'admin_items_browse_detailed_each',
            'public_items_show',
            'public_items_browse_each',
            'public_collections_show',
            'public_collections_browse_each',
        );

        $view = get_view();
        echo $view->partial(
            'plugins/stats-config-form.php',
            array(
                'displayByHooks' => $displayByHooks,
                'displayByHooksSelected' => unserialize(get_option('stats_display_by_hooks')) ?: array(),
        ));
    }

    /**
     * Saves plugin configuration page.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                if (in_array($optionKey, array(
                        'stats_roles_summary',
                        'stats_roles_browse_pages',
                        'stats_roles_browse_records',
                        'stats_roles_browse_downloads',
                        'stats_roles_browse_fields',
                        'stats_display_by_hooks',
                    ))) {
                   $post[$optionKey] = serialize($post[$optionKey]) ?: serialize(array());
                }
                set_option($optionKey, $post[$optionKey]);
            }
        }
    }

    /**
     * Defines the plugin's access control list.
     *
     * @param object $args
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];

        $table = array(
            'Stats_Summary' => array(
                'summary' => array(
                    'public' => 'stats_public_allow_summary',
                    'roles' => 'stats_roles_summary',
                    'privileges' => null,
                ),
            ),
            'Stats_Browse' => array(
                'browse_pages' => array(
                    'public' => 'stats_public_allow_browse_pages',
                    'roles' => 'stats_roles_browse_pages',
                    'privileges' => 'by-page',
                ),
                'browse_records' => array(
                    'public' => 'stats_public_allow_browse_records',
                    'roles' => 'stats_roles_browse_records',
                    'privileges' => 'by-record',
                ),
                'browse_downloads' => array(
                    'public' => 'stats_public_allow_browse_downloads',
                    'roles' => 'stats_roles_browse_downloads',
                    'privileges' => 'by-download',
                ),
                'browse_fields' => array(
                    'public' => 'stats_public_allow_browse_fields',
                    'roles' => 'stats_roles_browse_fields',
                    'privileges' => 'by-field',
                ),
            ),
        );

        foreach ($table as $resource => $rights) {
            $aclResource = new Zend_Acl_Resource($resource);
            $acl->addResource($aclResource);
            foreach ($rights as $right) {
                if (get_option($right['public'])) {
                    $acl->allow(null, $resource, $right['privileges']);
                }
                else {
                    $roles = get_option($right['roles']) ? unserialize(get_option($right['roles'])) : array();
                    // Check that all the roles exist, in case a plugin-added role has
                    // been removed (e.g. GuestUser).
                    foreach ($roles as $role) {
                        if ($acl->hasRole($role)) {
                            $acl->allow($role, $resource, $right['privileges']);
                        }
                    }
                }
            }
        }
    }

    /**
     * Defines route for direct download count.
     */
    public function hookDefineRoutes($args)
    {
        // ".htaccess" always redirects direct downloads to a public url.
        if (is_admin_theme()) {
            return;
        }

        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
    }

    /**
     * Called on each public page.
     */
    public function hookPublicHead($args)
    {
        $this->_logCurrentPage();
    }

    public function hookAdminItemsShowSidebar($args)
    {
        if ($this->_checkDisplayStatsByHook('admin_items_show_sidebar')) {
            $args['record'] = $args['item'];
            $this->_adminRecordsShowSidebar($args);
        }
    }

    public function hookAdminCollectionsShowSidebar($args)
    {
        if ($this->_checkDisplayStatsByHook('admin_collections_show_sidebar')) {
            $args['record'] = $args['collection'];
            $this->_adminRecordsShowSidebar($args);
        }
    }

    public function hookAdminFilesShowSidebar($args)
    {
        if ($this->_checkDisplayStatsByHook('admin_files_show_sidebar')) {
            $args['record'] = $args['file'];
            $this->_adminRecordsShowSidebar($args);
        }
    }

    protected function _adminRecordsShowSidebar($args)
    {
        $html = '<div class="panel">';
        $html .= '<h4>' . __('Stats') . '</h4>';
        $html .= $this->_resultRecord($args);
        $html .= '</div>';

        echo $html;
    }

    public function hookAdminItemsBrowseSimpleEach($args)
    {
        if ($this->_checkDisplayStatsByHook('admin_items_browse_simple_each')) {
            $view = $args['view'];
            $record = $args['item'];
            echo __('Views: %d (position %d)',
                $view->stats()->total_record($record, get_option('stats_default_user_status_admin')),
                $view->stats()->position_record($record, get_option('stats_default_user_status_admin')));
        }
    }

    public function hookAdminItemsBrowseDetailedEach($args)
    {
        if ($this->_checkDisplayStatsByHook('admin_items_browse_detailed_each')) {
            $args['record'] = $args['item'];

            $html = '<strong>' . __('Stats') . '</strong>';
            $html .= $this->_resultRecord($args);
            echo $html;
        }
    }

    protected function _resultRecord($args)
    {
        $view = $args['view'];
        $record = $args['record'];

        $html = '';
        $html .= '<ul>';
        $html .= '<li>';
        $html .= __('Views: %d (%d anonymous / %d identified users)',
            $view->stats()->total_record($record),
            $view->stats()->total_record($record, 'hits_anonymous'),
            $view->stats()->total_record($record, 'hits_identified'));
        $html .= '</li>';
        $html .= '<li>';
        $html .= __('Position: %d (%d anonymous / %d identified users)',
            $view->stats()->position_record($record),
            $view->stats()->position_record($record, 'hits_anonymous'),
            $view->stats()->position_record($record, 'hits_identified'));
        $html .= '</li>';
        $html .= '</ul>';
        return $html;
    }

    public function hookPublicItemsShow($args)
    {
        if ($this->_checkDisplayStatsByHook('public_items_show')) {
            $view = $args['view'];
            $record = $args['item'];
            echo $view->stats()->text_record($record);
        }
    }

    public function hookPublicItemsBrowseEach($args)
    {
        if ($this->_checkDisplayStatsByHook('public_items_show')) {
            $view = $args['view'];
            $record = $args['item'];
            echo $view->stats()->text_record($record);
        }
    }

    public function hookPublicCollectionsShow($args)
    {
        if ($this->_checkDisplayStatsByHook('public_collections_show')) {
            $view = $args['view'];
            $record = $args['collection'];
            echo $view->stats()->text_record($record);
        }
    }

    public function hookPublicCollectionsBrowseEach($args)
    {
        if ($this->_checkDisplayStatsByHook('public_collections_browse_each')) {
            $view = $args['view'];
            $record = $args['collection'];
            echo $view->stats()->text_record($record);
        }
    }

    /**
     * Adds browse in public navigation.
     */
    public function filterPublicNavigationMain($nav)
    {
        if (is_allowed('Stats_Summary', null)) {
            $nav[] = array(
                'label' => __('Stats'),
                'uri' => url('stats/summary'),
            );
        }
        return $nav;
    }

    /**
     * Adds browse in admin navigation.
     */
    public function filterAdminNavigationGlobal($nav)
    {
        $nav[] = array(
            'label' => __('Stats'),
            'uri' => url('stats/summary'),
            'resource' => 'Stats_Summary',
        );

        return $nav;
    }

    /**
     * Append section to admin dashboard
     *
     * @param array $stats Array of "statistics" displayed on dashboard
     * @return array
     */
    public function filterAdminDashboardStats($stats)
    {
        if (is_allowed('Stats_Summary', null)) {
            $inserted = array(
                sprintf('<a href="%s">%d</a>', url('stats/summary'), total_records('Hit')),
                __('hits'),
            );
            array_splice($stats, 5, 0, array($inserted));
        }
        return $stats;
    }

    public function filterAdminDashboardPanels($panels)
    {
        if (is_allowed('Stats_Summary', null)
                && $this->_checkDisplayStatsByHook('admin_dashboard')
            ) {
            $tableHit = $this->_db->getTable('Hit');
            $tableStat = $this->_db->getTable('Stat');
            $userStatus = get_option('stats_default_user_status_admin');

            $totalHits = $tableHit->count(array('user_status' => $userStatus));

            $html = '<h2>' . __('Stats') . '</h2>';

            $html .= '<br />';
            $html .= sprintf('<h4><a href="%s">%s</a></h4>', url('/stats/summary'), __('Total Hits: %d', $totalHits));

            $html .= '<ul>';
            foreach (array(
                    __('Last 30 days') => 30,
                    __('Last 7 days') => 7,
                    __('Last 24 hours') => 1,
                ) as $label => $day) {
                $html .= '<li>';
                $html .= sprintf("%s: %d",
                    $label,
                    $tableHit->count(array(
                        'since' => date('Y-m-d', strtotime("-$day days")),
                        'user_status' => $userStatus,
                    ))
                );
                $html .= '</li>';
            }
            $html .= '</ul>';

            if (is_allowed('Stats_Browse', 'by-page')) {
                $stats = $tableStat->getMostViewedPages(null, $userStatus, 5);
                $html .= sprintf('<h4><a href="%s">%s</a></h4>', url('/stats/browse/by-page'), __('Most viewed public pages'));
                if (empty($stats)) {
                    $html .= '<p>' . __('None') . '</p>';
                }
                else {
                    $html .= '<ol>';
                    foreach ($stats as $stat) {
                        $html .= '<li>';
                        $html .= __('%s (%d views)',
                                 // $stat->getPositionPage(),
                                 '<a href="' . WEB_ROOT . $stat->url . '">' . $stat->url . '</a>',
                                 $stat->$userStatus);
                         $html .= '</li>';
                    }
                    $html .= '</ol>';
                }
            }

            if (is_allowed('Stats_Browse', 'by-record')) {
                $stats = $tableStat->getMostViewedRecords('Item', $userStatus, 1);
                $html .= sprintf('<h4><a href="%s">%s</a></h4>', url('/stats/browse/by-record'), __('Most viewed public item'));
                if (empty($stats)) {
                    $html .= '<p>' . __('None') . '</p>';
                }
                else {
                    $stat = reset($stats);
                    $html .= '<ul>';
                    $html .= __('%s (%d views)',
                        $stat->Record ? link_to_item(null, array(), 'show', $stat->Record) : __('Deleted'),
                        $stat->$userStatus);
                    $html .= '</ul>';
                }
            }

            if (is_allowed('Stats_Browse', 'by-download')) {
                $stats = $tableStat->getMostViewedDownloads($userStatus, 1);
                $html .= sprintf('<h4><a href="%s">%s</a></h4>', url('/stats/browse/by-download'), __('Most downloaded file'));
                if (empty($stats)) {
                    $html .= '<p>' . __('None') . '</p>';
                }
                else {
                    $stat = reset($stats);
                    $html .= '<ul>';
                    $html .= __('%s (%d downloads)',
                        $stat->Record ? link_to_file_show(array(), null, $stat->Record) : __('Deleted'),
                        $stat->$userStatus);
                    $html .= '</ul>';
                }
            }

            if (is_allowed('Stats_Browse', 'by-field')) {
                $html .= sprintf('<h4><a href="%s">%s</a></h4>', url('/stats/browse/by-field'), __('Most frequent fields'));
                $html .= '<ul>';
                foreach (array(
                        'referrer' => __('Referrer'),
                        'query' => __('Query'),
                        'user_agent' => __('User Agent'),
                        'accept_language' => __('Accepted Language'),
                    ) as $field => $label) {
                    $hits = $tableHit->getMostFrequents($field, $userStatus, 1);
                    $html .= '<li>';
                    if (empty($hits)) {
                        $html .= __('%s: None', $label);
                    }
                    else {
                        $hit = reset($hits);
                        $html .= __('%s: %s (%d%%)', sprintf('<a href="%s">%s</a>', url('stats/browse/by-field?field=' . $field), $label), $hit[$field], $hit['hits'] * 100 / $totalHits);
                    }
                    $html .= '</li>';
                }
            }

            $panels[] = $html;
        }

        return $panels;
    }

    /**
     * Return the record associated to the current request.
     *
     * The record saved for a hit should be the dedicated one for a page, for
     * exemple ""/items/show/#" for the item number #.
     *
     * @param array of Omeka_Record_AbstractRecord $records
     *
     * @return Omeka_Record_AbstractRecord|null
     */
    public function filterStatsRecord($records, $args)
    {
        if (empty($records)) {
            $module = isset($args['module']) ? $args['module'] : 'default';
            $controller = isset($args['controller']) ? $args['controller'] : 'index';
            $action = isset($args['action']) ? $args['action'] : 'index';
            $record = null;
            switch ($module) {
                case 'default':
                    if ($action == 'show' && in_array($controller, array('items', 'collections', 'files'))) {
                        $record = Inflector::singularize($controller);
                    }
                    break;

                case 'simple-pages':
                    if ($controller == 'page' && $action == 'show') {
                        $record = 'simple_pages_page';
                    }
                    break;

                case 'exhibit-builder':
                    if ($controller == 'exhibits') {
                        if ($action == 'summary') {
                            $record = 'exhibit';
                        }
                        elseif ($action == 'show') {
                            $record = 'exhibit_page';
                        }
                    }
                    break;
            }

            if ($record) {
                $record = get_current_record($record, false);
                if (is_object($record)) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /**
     * Shortcode to display total hits of one or multiple pages or records.
     *
     * If record(s) is set, don't look for url(s).
     *
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeStatsTotal($args, $view)
    {
        $html = '';

        $result = null;
        $type = isset($args['type']) ? $args['type'] : null;
        $recordType = isset($args['record_type']) ? $args['record_type'] : null;
        $recordType = ucfirst(strtolower($recordType));
        $recordId = (!empty($recordType) && is_string($recordType) && isset($args['record_id']))
            ? $args['record_id']
            : null;

        // Search by record.
        if (!empty($recordId)) {
            $record = array('record_type' => $recordType, 'record_id' => $recordId);
            if ($type == 'download') {
                $result = $view->stats()->total_download($record);
            }
            else {
                $result = $view->stats()->total_record($record);
            }
        }
        // Search by record type.
        elseif (!empty($recordType)) {
            $result = $view->stats()->total_record_type($recordType);
        }
        // Search by url.
        else {
            $url = isset($args['url']) ? $args['url'] : current_url();
            $result = $view->stats()->total_page($url);
        }

        // Don't return null.
        $html .=  '<span class="stats-hits">';
        $html .= (integer) $result;
        $html .= '</span>';

        return $html;
    }

    /**
     * Shortcode to display the position of the page or record (most viewed).
     *
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeStatsPosition($args, $view)
    {
        $html = '';

        $result = null;
        $type = isset($args['type']) ? $args['type'] : null;
        // Different from StatsTotal, because position of multiple record_type
        // is meaningless.
        $recordType = isset($args['record_type']) && is_string($args['record_type'])
            ? $args['record_type']
            : null;
        $recordId = (!empty($recordType) && isset($args['record_id']))
            ? $args['record_id']
            : null;

        // Search by record.
        if (!empty($recordId)) {
            $record = array('record_type' => $recordType, 'record_id' => $recordId);
            if ($type == 'download') {
                $result = $view->stats()->position_download($record);
            }
            else {
                $result = $view->stats()->position_record($record);
            }
        }
        // Search by url.
        else {
            $url = isset($args['url']) ? $args['url'] : current_url();
            $result = $view->stats()->position_page($url);
        }

        // Don't return null.
        $html .=  '<span class="stats-position">';
        $html .= (integer) $result;
        $html .= '</span>';

        return $html;
    }

    /**
     * Shortcode to get the viewed pages or records.
     *
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeStatsVieweds($args, $view)
    {
        $html = '';

        $result = null;
        $type = isset($args['type']) ? $args['type'] : null;
        $sort = $this->_getArgumentSort($args);
        $limit = isset($args['number']) ? (integer) $args['number'] : 10;
        $offset = isset($args['offset']) ? (integer) $args['offset'] : null;

        // Search by record type.
        if (isset($args['type'])) {
            $html .= $view->stats()->viewed_records($type, $sort, null, $limit, $offset, true);
        }
        // Search in all pages.
        else {
            $html .= $view->stats()->viewed_pages(null, $sort, null, $limit, $offset, true);
        }

        return $html;
    }

    /**
     * Log the hit on the current page.
     */
    protected function _logCurrentPage()
    {
        $hit = new Hit;
        $hit->setCurrentHit();
        $hit->save();
    }

    /**
     * Helper to determine if the stats is set to be displayed by hook in the
     * specified page.
     *
     * @param string $hookPage Hook for the page.
     *
     * @return boolean
     */
    protected function _checkDisplayStatsByHook($hookPage)
    {
        $statsDisplayByHooks = unserialize(get_option('stats_display_by_hooks'));
        return in_array($hookPage, $statsDisplayByHooks);
    }

    /**
     * Extract sort from args.
     *
     * @param array $args
     * @return string sort.
     */
    private function _getArgumentSort($args)
    {
        if (isset($args['sort']) && in_array($args['sort'], array('most', 'last')))  {
            return $args['sort'];
        }
        return 'most';
    }
}
