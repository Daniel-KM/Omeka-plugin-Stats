<?php
$pageTitle = __('Stats (%s total)', $total_results);
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
        <strong><?php echo __('By %s (%s)', $label_field, $this->stats()->human_user_status($user_status)); ?></strong>
        <em><?php echo ' ' . __('[%d filled values / %d total hits]', $total_not_empty, $total_hits); ?></em>
    </p>
    <?php echo common('quick-filters', array('stats_type' => $stats_type)); ?>
<?php if ($total_results):
    echo pagination_links();
    ?>
    <table class="stats-table">
    <thead>
        <tr>
            <?php
            $browseHeadings[$label_field] = $field;
            $browseHeadings[__('Hits')] = 'hits';
            $browseHeadings['%'] = 'hits';
            echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => ''));
            ?>
        </tr>
    </thead>
    <tbody>
<?php $key = 0; ?>
<?php foreach ($hits as $position => $hit): ?>
        <tr class="stats-stat <?php if (++$key % 2 == 1) echo 'odd'; else echo 'even'; ?>">
            <td class="stats-field"><?php echo $hit[$field]; ?></td>
            <td class="stats-hits"><?php echo $hit['hits']; ?></td>
            <td class="stats-percent"><?php echo round($hit['hits'] * 100 / $total_not_empty, 1); ?>%</td>
        </tr>
<?php endforeach; ?>
    </tbody>
    </table>
    <?php echo pagination_links(); ?>
<?php else: ?>
    <br class="clear" />
    <?php if (total_records('Hit') == 0): ?>
        <h2><?php echo __('There is no hit yet.'); ?></h2>
    <?php else: ?>
        <p><?php echo __('The query searched %s hits and returned no results.', total_records('Hit')); ?></p>
        <p><a href="<?php echo url('stats/browse/by-' . $stats_type); ?>"><?php echo __('See all stats.'); ?></a></p>
    <?php endif; ?>
<?php endif; ?>
    <?php echo common('quick-filters', array('stats_type' => $stats_type)); ?>
</div>
<?php echo foot(); ?>
