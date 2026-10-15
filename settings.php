<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Administrator settings.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_eledia_xmas', get_string('pluginname', 'local_eledia_xmas'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $component = 'local_eledia_xmas';
        // Starting the preview changes the session, so it is a POST form; single_button adds the sesskey.
        $settings->add(new admin_setting_heading('local_eledia_xmas/preview', '', $OUTPUT->render(new single_button(
            new moodle_url('/local/eledia_xmas/preview.php', ['action' => 'start']),
            get_string('preview', $component),
            'post'
        ))));
        foreach (['enabled' => 0, 'scheduled' => 1, 'ledges' => 1, 'fullcover' => 0, 'eastereggs' => 1] as $key => $default) {
            $settings->add(new admin_setting_configcheckbox(
                "$component/$key",
                get_string($key, $component),
                get_string($key . '_desc', $component),
                $default
            ));
        }
        $settings->add(new admin_setting_configselect(
            "$component/contrast",
            get_string('contrast', $component),
            get_string('contrast_desc', $component),
            'subtle',
            ['subtle' => get_string('contrast_subtle', $component), 'strong' => get_string('contrast_strong', $component)]
        ));
        foreach (['start' => ['day' => 1, 'month' => 12], 'end' => ['day' => 6, 'month' => 1]] as $prefix => $default) {
            $settings->add(new \local_eledia_xmas\admin_setting_seasondate(
                $prefix,
                get_string($prefix . 'date', $component),
                get_string('dates_desc', $component),
                $default
            ));
        }
        $pages = [];
        foreach (['site-index', 'my-index', 'my-courses', 'course'] as $page) {
            $pages[$page] = get_string('page_' . $page, $component);
        }
        $settings->add(new admin_setting_configmultiselect(
            "$component/pages",
            get_string('pages', $component),
            get_string('pages_desc', $component),
            ['site-index', 'my-index', 'my-courses'],
            $pages
        ));
        $settings->add(new admin_setting_configselect(
            "$component/idledelay",
            get_string('idledelay', $component),
            get_string('idledelay_desc', $component),
            60,
            [30 => '30 s', 60 => '60 s', 120 => '120 s', 300 => '300 s']
        ));
        $settings->add(new admin_setting_configselect(
            "$component/intensity",
            get_string('intensity', $component),
            get_string('intensity_desc', $component),
            '1',
            ['0.5' => get_string('gentle', $component), '1' => get_string('normal', $component),
                '1.5' => get_string('strong', $component)]
        ));
    }
}
