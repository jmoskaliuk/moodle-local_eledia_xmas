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
 * Administrator-only preview.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
$action = optional_param('action', '', PARAM_ALPHA);
// Session changes are accepted only from a submitted POST form; a plain GET just returns to the settings.
if (($action === 'start' || $action === 'stop') && data_submitted()) {
    require_sesskey();
    if ($action === 'start') {
        $SESSION->local_eledia_xmas_preview = ['userid' => (int)$USER->id, 'until' => time() + HOURSECS];
        redirect(new moodle_url('/', ['redirect' => 0]));
    }
    unset($SESSION->local_eledia_xmas_preview);
}
redirect(new moodle_url('/admin/settings.php', ['section' => 'local_eledia_xmas']));
