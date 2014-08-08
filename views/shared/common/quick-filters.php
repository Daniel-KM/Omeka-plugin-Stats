<ul class="quick-filter-wrapper">
    <li><a href="#" tabindex="0"><?php echo __('Quick Filter'); ?></a>
    <ul class="dropdown">
        <li><span class="quick-filter-heading"><?php echo __('Quick Filter') ?></span></li>
<?php
$base_url = 'stats/browse/by-' . $stats_type;
switch ($stats_type):
    case 'page': ?>
        <li><a href="<?php echo url($base_url); ?>"><?php echo __('View All') ?></a></li>
        <li><a href="<?php echo url($base_url, array('has_record' => true)); ?>"><?php echo __('With record') ?></a></li>
        <li><a href="<?php echo url($base_url, array('has_record' => false)); ?>"><?php echo __('Without record') ?></a></li>
        <?php break;

    case 'record': ?>
        <li><a href="<?php echo url($base_url); ?>"><?php echo __('View All') ?></a></li>
        <li><a href="<?php echo url($base_url, array('record_type' => 'Item')); ?>"><?php echo __('By Item'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('record_type' => 'Collection')); ?>"><?php echo __('By Collection'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('record_type' => 'File')); ?>"><?php echo __('By File'); ?></a></li>
        <?php if (plugin_is_active('SimplePages')): ?>
        <li><a href="<?php echo url($base_url, array('record_type' => 'SimplePagesPage')); ?>"><?php echo __('By Simple Page'); ?></a></li>
        <?php endif; ?>
        <?php if (plugin_is_active('ExhibitBuilder')): ?>
        <li><a href="<?php echo url($base_url, array('record_type' => 'Exhibit')); ?>"><?php echo __('By Exhibit'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('record_type' => 'ExhibitPage')); ?>"><?php echo __('By Exhibit Page'); ?></a></li>
        <?php endif; ?>
        <?php break;

    case 'field': ?>
        <li><a href="<?php echo url($base_url, array('field' => 'referrer')); ?>"><?php echo __('Referrers'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('field' => 'query')); ?>"><?php echo __('Queries'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('field' => 'accept_language')); ?>"><?php echo __('Languages'); ?></a></li>
        <li><a href="<?php echo url($base_url, array('field' => 'user_agent')); ?>"><?php echo __('Browsers'); ?></a></li>
        <?php break;
endswitch; ?>
    </ul>
    </li>
</ul>
