<fieldset id="fieldset-stats-rights"><legend><?php echo __('Rights and Roles'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_browse_roles', __('Table of Rights')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <p class="explanation">
                    <?php echo __('Select access rights for each stats page and each role.');
                    echo '<br />' . __('If "public" is checked, all people will have access to the selected data.');
                    echo '<br />' . __('To get stats about direct download of original files, a line should be added in ".htaccess".');
                    echo '<br />' . __("%sWarning%s: Shortcodes, helpers and hooks don't follow any rule.", '<strong>', '</strong>'); ?>
                </p>
                <?php
                    $table = array(
                        'summary' => array(
                            'label' => __('View Summary'),
                            'public' => 'stats_public_allow_summary',
                            'roles' => 'stats_roles_summary',
                        ),
                        'browse_pages' => array(
                            'label' => __('Browse by Page'),
                            'public' => 'stats_public_allow_browse_pages',
                            'roles' => 'stats_roles_browse_pages',
                        ),
                        'browse_records' => array(
                            'label' => __('Browse by Record'),
                            'public' => 'stats_public_allow_browse_records',
                            'roles' => 'stats_roles_browse_records',
                        ),
                        'browse_downloads' => array(
                            'label' => __('Browse by Download'),
                            'public' => 'stats_public_allow_browse_downloads',
                            'roles' => 'stats_roles_browse_downloads',
                        ),
                        'browse_fields' => array(
                            'label' => __('Browse by Field'),
                            'public' => 'stats_public_allow_browse_fields',
                            'roles' => 'stats_roles_browse_fields',
                        ),
                    );
                    $userRoles = get_user_roles();
                    unset($userRoles['super']);
                ?>
                <table class="stats-righs">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php echo __('Public'); ?></th>
                        <?php foreach ($userRoles as $role => $label): ?>
                        <th><?php echo $label; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $key = 0;
                    foreach ($table as $name => $right):
                        $currentRole = $right['roles'];
                        $currentRoles = get_option($currentRole) ? unserialize(get_option($currentRole)) : array();
                        printf('<tr class="%s">', (++$key % 2 == 1) ? 'odd' : 'even');
                        echo '<td>' . $right['label'].  '</td>';
                        echo '<td>';
                        echo $this->formCheckbox($right['public'], true,
                            array('checked' => (boolean) get_option($right['public'])));
                        echo '</td>';
                        foreach ($userRoles as $role => $label):
                            echo '<td>';
                            echo $this->formCheckbox($currentRole . '[]', $role,
                                array('checked' => in_array($role, $currentRoles) ? 'checked' : ''));
                            echo '</td>';
                        endforeach;
                        echo '</tr>';
                    endforeach;
                ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-stats-per-page"><legend><?php echo __('Browse Stats'); ?></legend>
    <p><?php
        echo __('These options allow to restrict stats according to status of users.')
            . ' ' . __('They are used with hooks, helpers and shortcodes, not with direct queries.');
    ?></p>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_default_user_status_admin', __('User status for admin pages')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formRadio('stats_default_user_status_admin',
                get_option('stats_default_user_status_admin'),
                null,
                array(
                    'hits' => __('Total hits'),
                    'hits_anonymous' => __('Anonymous'),
                    'hits_identified' => __('Identified users'),
                )); ?>
            <p class="explanation">
                <?php echo __('Choose the default status of users for stats in admin pages.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_default_user_status_public', __('User status for public pages')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formRadio('stats_default_user_status_public',
                get_option('stats_default_user_status_public'),
                null,
                array(
                    'hits' => __('Total hits'),
                    'hits_anonymous' => __('Anonymous'),
                    'hits_identified' => __('Identified users'),
                )); ?>
            <p class="explanation">
                <?php echo __('Choose the status of users to restrict stats in public pages.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_per_page_admin', __('Results Per Page (admin)')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <?php echo $this->formText('stats_per_page_admin',
                    get_option('stats_per_page_admin')); ?>
                <p class="explanation">
                    <?php echo __('Limit the number of results displayed per page in the administrative interface.'); ?>
                </p>
            </div>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_per_page_public', __('Results Per Page (public)')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <?php echo $this->formText('stats_per_page_public',
                    get_option('stats_per_page_public')); ?>
                <p class="explanation">
                    <?php echo __('Limit the number of results displayed per page in the public interface.'); ?>
                </p>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-stats-display-by-hooks"><legend><?php echo __('Display by Hooks'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_display_by_hooks', __('Pages where hits are shown via hooks')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <ul>
                <?php
                    foreach ($displayByHooks as $page) {
                        echo '<li>';
                        echo $this->formCheckbox('stats_display_by_hooks[]', $page,
                            array('checked' => in_array($page, $displayByHooksSelected) ? 'checked' : ''));
                        echo $page;
                        echo '</li>';
                    }
                ?>
                </ul>
                <p class="explanation">
                    <?php echo __('These options allow to parameter the pages where the htis are displayed.');
                    echo ' ' . __('In any case, this is the theme that manages last if hits are displayed or not.'); ?>
                </p>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-stats-privacy"><legend><?php echo __('Privacy'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_privacy', __('Level of Privacy')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formRadio('stats_privacy',
                get_option('stats_privacy'),
                null,
                array(
                    'anonymous' => __('Anonymous'),
                    'hashed' => __('Hashed IP'),
                    'partial_1' => __('Partial IP (first hex)'),
                    'partial_2' => __('Partial IP (first 2 hexs)'),
                    'partial_3' => __('Partial IP (first 3 hexs)'),
                    'clear' => __('Clear IP'),
                )); ?>
            <p class="explanation">
                <?php echo __('Choose the level of privacy (default: hashed IP).')
                    . ' ' . __('A change applies only to new hits.');
                ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-stats-misc"><legend><?php echo __('Misc'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('stats_excludebots', __('Exclude crawlers/bots')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php 
                echo $this->formCheckbox('stats_excludebots', true,
                    array('checked' => (boolean) get_option("stats_excludebots")));
            ?>
            <p class="explanation">
                <?php echo __('By checking this box, all hits which user agent contains the term "bot", "crawler", "spider", etc. will be excluded.'); ?>
            </p>
        </div>
    </div>
</fieldset>
