<div class="stats-stat">
    <p><?php
    if ($type == 'download'):
        if (empty($stat)):
            echo __('No download');
        else:
            echo __('%s (%s downloads): %s [%s]',
                '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
                '<span class="stats-hits">' . $stat->$user_status . '</span>',
                '<a href="' . url($stat->url) . '"><span class="stats-url">' . $stat->url . '</span></a>',
                '<span class="stats-record-type">' . $stat->getHumanRecordType(__('No specific record')) . '</span>'
            );
        endif;
    else:
        if (empty($stat)):
            echo __('No view');
        else:
            echo __('%s (%s views): %s [%s]',
                '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
                '<span class="stats-hits">' . $stat->$user_status . '</span>',
                '<a href="' . url($stat->url) . '"><span class="stats-url">' . $stat->url . '</span></a>',
                '<span class="stats-record-type">' . $stat->getHumanRecordType(__('No specific record')) . '</span>'
            );
        endif;
    endif;
    ?></p>
</div>
