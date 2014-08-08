<?php
$pageTitle = __('Stats');
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'stats index',
    'content_class' => 'horizontal-nav',
));
echo common('stats-nav');
?>
<div id="primary">
    <h1 title="<?php echo $pageTitle; ?>" class="section-title"><?php echo $pageTitle; ?></h1>
    <?php echo flash(); ?>
    <h2><?php echo  __('Total Hits: %d', $results['all']);
    ?></h2>
    <h3><?php echo  __('Today: %d', $results['today']);
    ?></h3>
<section class="three columns alpha">
    <div class="panel">
        <h2><?php echo __('History'); ?></h2>
        <ul>
        <?php foreach ($results['history'] as $label => $value): ?>
            <li><?php
                printf('%s: %d', $label, $value);
            ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<section class="four columns">
    <div class="panel">
        <h2><?php echo __('Current'); ?></h2>
        <ul>
        <?php foreach ($results['current'] as $label => $value): ?>
            <li><?php
                printf('%s: %d', $label, $value);
            ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<section class="three columns omega">
    <div class="panel">
        <h2><?php echo __('Rolling Period'); ?></h2>
        <ul>
        <?php foreach ($results['rolling'] as $label => $value): ?>
            <li><?php
                printf('%s: %d', $label, $value);
            ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php if (isset($results['most_vieweds_pages'])): ?>
<section class="ten columns alpha omega">
    <div class="panel">
        <h2><a href="<?php echo url('/stats/browse/by-page'); ?>"><?php echo __('Most viewed public pages'); ?></a></h2>
        <?php if (empty($results['most_vieweds_pages'])): ?>
        <p><?php echo __('None'); ?></p>
        <?php else: ?>
        <ol>
        <?php foreach ($results['most_vieweds_pages'] as $position => $stat): ?>
            <li><?php
                echo __('%s (%d views)',
                     '<a href="' . WEB_ROOT . $stat->url . '">' . $stat->url . '</a>',
                     $stat->$user_status);
            ?></li>
        <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php if (isset($results['most_vieweds_records'])): ?>
<section class="ten columns alpha omega">
    <div class="panel">
        <h2><a href="<?php echo url('/stats/browse/by-record'); ?>"><?php echo __('Most viewed public records'); ?></a></h2>
        <?php if (empty($results['most_vieweds_records'])): ?>
        <p><?php echo __('None'); ?></p>
        <?php else: ?>
        <ol>
        <?php foreach ($results['most_vieweds_records'] as $position => $stat): ?>
            <li><?php
                echo __('%s (%d views)',
                    $this->stats()->link_to_record($stat->Record),
                     $stat->$user_status);
            ?></li>
        <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php if (isset($results['most_frequent_fields'])):
    $labels = array(
        'referrer' => __('Most frequent external referrers'),
        'query' =>__('Most frequent queries'),
        'accept_language' => __('Most frequent accepted languages'),
        'user_agent' => __('Most frequent browsers'),
    );
    foreach ($results['most_frequent_fields'] as $field => $result): ?>
<section class="ten columns alpha omega">
    <div class="panel">
        <h2><a href="<?php echo url('/stats/browse/by-field?field=' . $field); ?>"><?php echo $labels[$field]; ?></a></h2>
        <?php echo common('most-frequents', array(
            'result' => $result,
            'field' => $field,
        )); ?>
    </div>
</section>
    <?php endforeach;
endif; ?>
<?php fire_plugin_hook('stats_summary', array('view' => $this)); ?>
</div>
<?php echo foot(); ?>
