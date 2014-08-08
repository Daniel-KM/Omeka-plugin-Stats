<nav id="section-nav" class="navigation vertical">
<?php
    $navArray = array();
    if (is_allowed('Stats_Summary', null)) {
        $navArray[] = array(
            'label' => __('Summary'),
            'action' => 'index',
            'controller' => 'summary',
            'module' => 'stats',
        );
    }
    if (is_allowed('Stats_Browse', 'by-page')) {
        $navArray[] = array(
            'label' => __('By Page'),
            'action' => 'by-page',
            'controller' => 'browse',
            'module' => 'stats',
        );
    }
    if (is_allowed('Stats_Browse', 'by-record')) {
        $navArray[] = array(
            'label' => __('By Record'),
            'action' => 'by-record',
            'controller' => 'browse',
            'module' => 'stats',
        );
    }
    if (is_allowed('Stats_Browse', 'by-field')) {
        $navArray[] = array(
            'label' => __('By Field'),
            'action' => 'by-field',
            'controller' => 'browse',
            'module' => 'stats',
        );
    }
    echo nav($navArray, 'admin_navigation_settings');
?>
</nav>
