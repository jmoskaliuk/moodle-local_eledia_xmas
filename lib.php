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
 * Navigation callbacks.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Link the page that removes this user's eLeDia | Xmas entries from the current browser.
 *
 * @param navigation_node $navigation User account node on the preferences page.
 * @param stdClass $user User whose preferences are shown.
 * @param context_user $usercontext User context.
 * @param stdClass $course Current course.
 * @param context_course $coursecontext Course context.
 */
function local_eledia_xmas_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext): void {
    global $USER;
    // Browser storage can only be cleared by its own user, and only matters where the plugin runs.
    if ($USER->id != $user->id || isguestuser() || !get_config('local_eledia_xmas', 'enabled')) {
        return;
    }
    $navigation->add(
        get_string('forget', 'local_eledia_xmas'),
        new moodle_url('/local/eledia_xmas/forget.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_eledia_xmas_forget'
    );
}
