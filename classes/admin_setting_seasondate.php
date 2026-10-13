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
 * Annual day and month selector preserving the existing configuration keys.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_eledia_xmas;

/**

 * A yearless date for the recurring season.

 */
class admin_setting_seasondate extends \admin_setting {
    /**
     * @var string Configuration key for the month.
     */
    private string $monthname;

    /**
     * Define one grouped date setting.
     * @param string $prefix Start or end.
     * @param string $label Translated label.
     * @param string $description Translated help.
     * @param array $default Default day and month.
     */
    public function __construct(string $prefix, string $label, string $description, array $default) {
        $this->monthname = $prefix . 'month';
        parent::__construct('local_eledia_xmas/' . $prefix . 'day', $label, $description, $default);
    }

    /**
     * Read both legacy keys.
     * @return array|null Day and month, or unset.
     */
    public function get_setting() {
        $day = $this->config_read($this->name);
        $month = $this->config_read($this->monthname);
        return $day === null || $month === null ? null : ['day' => $day, 'month' => $month];
    }

    /**
     * Validate the complete date before saving either part. February 29 is valid annually.
     * @param mixed $data Submitted selection.
     * @return string Empty on success, translated error otherwise.
     */
    public function write_setting($data) {
        $day = is_array($data) ? filter_var($data['day'] ?? null, FILTER_VALIDATE_INT) : false;
        $month = is_array($data) ? filter_var($data['month'] ?? null, FILTER_VALIDATE_INT) : false;
        if (!$day || !$month || !checkdate($month, $day, 2000)) {
            return get_string('invaliddate', 'local_eledia_xmas');
        }
        $saved = $this->config_write($this->name, $day) && $this->config_write($this->monthname, $month);
        return $saved ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Render paired, individually labelled native selects with translated month names.
     * @param mixed $data Current day and month.
     * @param string $query Search term.
     * @return string Settings row.
     */
    public function output_html($data, $query = '') {
        $data = is_array($data) ? $data : $this->get_defaultsetting();
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = userdate(gmmktime(12, 0, 0, $month, 1, 2000), '%B', 0);
        }
        $fields = '';
        foreach (['day' => array_combine(range(1, 31), range(1, 31)), 'month' => $months] as $part => $choices) {
            $id = $this->get_id() . '_' . $part;
            $label = $this->visiblename . ': ' . get_string($part);
            $fields .= \html_writer::label($label, $id, false, ['class' => 'sr-only']);
            $fields .= \html_writer::select(
                $choices,
                $this->get_full_name() . '[' . $part . ']',
                $data[$part],
                null,
                ['id' => $id, 'class' => 'custom-select',
                'disabled' => $this->is_readonly() ? 'disabled' : null]
            );
        }
        $element = \html_writer::div($fields, 'local-eledia-xmas-seasondate', ['role' => 'group',
            'aria-label' => $this->visiblename]);
        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            $this->get_id() . '_day',
            '',
            null,
            $query
        );
    }
}
