<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_morsle/google_admin', get_string('google_admin', 'block_morsle'),
                    get_string('google_admin_info', 'block_morsle'), '', PARAM_EMAIL, 50));
}


