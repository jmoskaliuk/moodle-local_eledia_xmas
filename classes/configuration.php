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
 * Season and page eligibility.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_xmas;

/**
 * Configuration and eligibility for seasonal decoration.
 */
class configuration {
    /**
     * Read settings with safe defaults and bounded browser parameters.
     *
     * @return \stdClass
     */
    public static function get(): \stdClass {
        $config = get_config('local_eledia_xmas');
        $defaults = [
            'enabled' => 0, 'scheduled' => 1, 'eastereggs' => 1, 'startmonth' => 12, 'startday' => 1,
            'endmonth' => 1, 'endday' => 6, 'pages' => 'site-index,my-index,my-courses',
            'contrast' => 'subtle', 'idledelay' => 60, 'intensity' => 1, 'fullcover' => 0, 'ledges' => 1,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($config->$key)) {
                $config->$key = $value;
            }
        }
        $config->idledelay = max(15, min(600, (int)$config->idledelay));
        $config->intensity = max(0.5, min(1.5, (float)$config->intensity));
        $config->contrast = $config->contrast === 'strong' ? 'strong' : 'subtle';
        return $config;
    }

    /**
     * Check a recurring season, including seasons spanning New Year, in the site timezone.
     *
     * @param \stdClass $config Plugin settings.
     * @param int $timestamp Unix time to check.
     * @return bool
     */
    public static function in_season(\stdClass $config, int $timestamp): bool {
        if (empty($config->scheduled)) {
            return true;
        }
        $date = new \DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone(\core_date::get_server_timezone_object());
        $year = (int)$date->format('Y');
        $values = [];
        foreach (['start', 'end'] as $prefix) {
            $month = (int)$config->{$prefix . 'month'};
            $day = (int)$config->{$prefix . 'day'};
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return false;
            }
            $monthstart = $date->setDate($year, $month, 1);
            // A configured 31st or February 29th resolves to the final valid day of that month.
            $values[] = $month * 100 + min($day, (int)$monthstart->format('t'));
        }
        [$start, $end] = $values;
        $today = (int)$date->format('md');
        return $start <= $end ? $today >= $start && $today <= $end : $today >= $start || $today <= $end;
    }

    /**
     * Name a season by the year it starts, so a season spanning New Year keeps one identity.
     *
     * @param \stdClass $config Plugin settings.
     * @param int $timestamp Unix time to check.
     * @return int Year in which the current or most recent season started.
     */
    public static function season_year(\stdClass $config, int $timestamp): int {
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_server_timezone_object());
        $year = (int)$date->format('Y');
        $start = (int)$config->startmonth * 100 + (int)$config->startday;
        $end = (int)$config->endmonth * 100 + (int)$config->endday;
        if (!empty($config->scheduled) && $start > $end && (int)$date->format('md') < $start) {
            return $year - 1;
        }
        return $year;
    }

    /**
     * Decide whether a normal page should receive the effect.
     *
     * @param \moodle_page $page Page being rendered.
     * @param \stdClass $config Settings.
     * @param int $timestamp Current time.
     * @return bool
     */
    public static function allowed(\moodle_page $page, \stdClass $config, int $timestamp): bool {
        if (empty($config->enabled) || !isloggedin() || isguestuser() || $page->user_is_editing()) {
            return false;
        }
        if (!in_array($page->pagelayout, ['standard', 'course', 'incourse', 'frontpage', 'mydashboard', 'mycourses'])) {
            return false;
        }
        $pages = explode(',', $config->pages);
        $type = $page->pagetype;
        $allowed = in_array($type, ['site-index', 'my-index', 'my-courses']) && in_array($type, $pages);
        if (str_starts_with($type, 'course-view-') && in_array('course', $pages)) {
            $allowed = true;
        }
        return $allowed && self::in_season($config, $timestamp);
    }
}
