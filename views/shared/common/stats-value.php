<div class="stats-stat">
    <p><?php
    if ($type == 'download'):
        if (empty($stat)):
            echo __('Not downloaded');
        else:
            echo __('Position: %s (%s downloads)',
                '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
                '<span class="stats-hits">' . $stat->$user_status . '</span>'
            );
        endif;
    else:
        if (empty($stat)):
            echo __('Not viewed');
        else:
            echo __('Position: %s (%s views)',
                '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
                '<span class="stats-hits">' . $stat->$user_status . '</span>'
            );
        endif;
    endif;
    ?></p>
</div>
