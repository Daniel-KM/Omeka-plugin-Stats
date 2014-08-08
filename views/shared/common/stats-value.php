<div class="stats-stat">
    <p><?php
    if (empty($stat)):
        echo __('Not viewed');
    else:
        echo __('Position: %s (%s views)',
            '<span class="stats-position">' . $stat->getPosition($user_status) . '</span>',
            '<span class="stats-hits">' . $stat->$user_status . '</span>'
        );
    endif;
    ?></p>
</div>
