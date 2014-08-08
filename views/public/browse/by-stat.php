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
    <h1 title="<?php echo $pageTitle; ?>" class="section-title"><?php echo $pageTitle; ?></h1>
    <?php echo flash(); ?>
    <?php echo common('quick-filters', array('stats_type' => $stats_type)); ?>
<?php if ($total_results):
    echo pagination_links();
    ?>
    <table class="stats-table" cellspacing="0" cellpadding="0">
    <thead>
        <tr>
            <?php
            $browseHeadings[__('Page')] = 'url';
            $browseHeadings[__('Hits')] = $user_status;
            $browseHeadings[__('Dedicated Record')] = null;
            $browseHeadings[__('Record Type')] = 'record_type';
            $browseHeadings[__('Date')] = 'modified';
            echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => ''));
            ?>
        </tr>
    </thead>
    <tbody>
        <?php $key = 0; ?>
        <?php foreach (loop('stats') as $stat): ?>
        <tr class="stats-stat <?php if (++$key % 2 == 1) echo 'odd'; else echo 'even'; ?>">
            <td class="stats-url">
                <div class="stats-hover">
                    <a href="<?php echo WEB_ROOT . $stat->url; ?>"><?php echo $stat->url; ?></span>
                </div>
            </td>
            <td class="stats-hits">
                <?php echo $stat->$user_status; ?>
            </td>
            <td class="stats-record">
                <?php if ($stat->hasRecord()): ?>
                <div class="stats-hover">
                    <?php echo $this->stats()->link_to_record($stat->Record); ?>
                </div>
                <?php endif; ?>
            </td>
            <td class="stats-record-type">
                <?php if ($stat->hasRecord()):
                    echo $stat->getHumanRecordType();
                endif; ?>
            </td>
            <td>
                <?php echo html_escape(format_date($stat->modified, Zend_Date::DATETIME_SHORT)); ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    </table>
    <?php echo pagination_links(); ?>
<?php else: ?>
    <br class="clear" />
    <?php if (total_records('Stat') == 0): ?>
        <h2><?php echo __('There is no stat yet.'); ?></h2>
    <?php else: ?>
        <p><?php echo __('The query searched %s rows and returned no results.', total_records('Stat')); ?></p>
        <p><a href="<?php echo url('stats/browse/by-' . $stats_type); ?>"><?php echo __('See all stats.'); ?></a></p>
    <?php endif; ?>
<?php endif; ?>
    <?php echo common('quick-filters', array('stats_type' => $stats_type)); ?>
</div>
<?php echo foot(); ?>
