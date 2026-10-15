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
 * Remove this user's eLeDia | Xmas entries from the current browser.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/'));
}
$PAGE->set_url(new moodle_url('/local/eledia_xmas/forget.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('forget', 'local_eledia_xmas'));
$PAGE->set_heading(fullname($USER));
// The entries live only in the browser, so the removal itself runs there.
$PAGE->requires->js_call_amd('local_eledia_xmas/forget', 'init', [\local_eledia_xmas\configuration::storage_key((int)$USER->id)]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forget', 'local_eledia_xmas'));
echo $OUTPUT->render_from_template('local_eledia_xmas/forget', []);
echo $OUTPUT->footer();
