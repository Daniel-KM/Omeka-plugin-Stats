<?php
$pageTitle = __('Stats');
queue_css_file('stats');
echo head(array(
    'title' => $pageTitle,
    'bodyclass' => 'stats browse',
    'content_class' => 'horizontal-nav',
));
echo common('stats-nav');
?>
<div id="primary">
    <?php echo flash(); ?>
    <p>
        <strong><?php echo __('By Collection'); ?></strong>
    </p>
<?php if ($total_results): ?>
    <table class="stats-table">
    <thead>
        <tr>
            <?php
            $browseHeadings[__('Collection')] = 'collection';
            $browseHeadings[__('Hits')] = 'hits';
            if (plugin_is_active('CollectionTree')) {
                $browseHeadings[__('Hits (including sub-collections)')] = 'hitsInclusive';
            }
            echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => ''));
            ?>
        </tr>
    </thead>
    <tbody>
<?php foreach ($hits as $position => $hit): ?>
        <tr class="stats-stat <?php if ($position % 2 == 0) echo 'odd'; else echo 'even'; ?>">
            <td class="stats-field"><?php echo $hit['collection']; ?></td>
            <td class="stats-hits"><?php echo $hit['hits']; ?></td>
            <?php if (plugin_is_active('CollectionTree')): ?>
                <td class="stats-hitsinclusive"><?php echo $hit['hitsInclusive']; ?></td>
            <?php endif; ?>
        </tr>
<?php endforeach; ?>
    </tbody>
    </table>
<?php else: ?>
    <br class="clear" />
    <?php if (total_records('Hit') == 0): ?>
        <h2><?php echo __('There is no hit yet.'); ?></h2>
    <?php else: ?>
        <p><?php echo __('The query searched %s hits and returned no results.', total_records('Hit')); ?></p>
        <p><a href="<?php echo url('stats/browse/by-collection'); ?>"><?php echo __('See all stats.'); ?></a></p>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php echo foot(); ?>
