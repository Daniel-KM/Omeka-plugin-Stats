<div class="stats-stat">
    <p><?php
        echo __('%s (%s views): %s [%s]',
            '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
            '<span class="stats-hits">' . $stat->$user_status . '</span>',
            '<a href="' . url($stat->url) . '"><span class="stats-url">' . $stat->url . '</span></a>',
            '<span class="stats-record-type">' . $stat->getHumanRecordType(__('No specific record')) . '</span>'
        );
    ?></p>
</div>
