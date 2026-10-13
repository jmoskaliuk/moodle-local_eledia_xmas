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

namespace local_eledia_xmas;

/**
 * Verify season boundaries, eligibility and safe configuration.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_xmas\configuration
 */
final class configuration_test extends \advanced_testcase {
    /**
     * Test inclusive New Year boundaries using site rather than user timezone.
     */
    public function test_recurring_season(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->timezone = 'Europe/Berlin';
        $config = configuration::get();
        foreach (
            [
            '2026-11-30 22:59:59 UTC' => false,
            '2026-11-30 23:00:00 UTC' => true,
            '2027-01-06 22:59:59 UTC' => true,
            '2027-01-06 23:00:00 UTC' => false,
            '2027-07-01 00:00:00 UTC' => false,
            '2027-12-10 00:00:00 UTC' => true,
            ] as $date => $expected
        ) {
            $this->assertSame($expected, configuration::in_season($config, strtotime($date)), $date);
        }
    }

    /**
     * Test that a season spanning New Year keeps the year in which it started.
     */
    public function test_season_year(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->timezone = 'Europe/Berlin';
        $config = configuration::get();
        foreach (
            [
            '2026-11-30 23:00:00 UTC' => 2026,
            '2026-12-31 22:59:59 UTC' => 2026,
            '2026-12-31 23:00:00 UTC' => 2026,
            '2027-01-06 22:00:00 UTC' => 2026,
            '2027-12-01 00:00:00 UTC' => 2027,
            ] as $date => $expected
        ) {
            $this->assertSame($expected, configuration::season_year($config, strtotime($date)), $date);
        }
        $config->startmonth = 5;
        $config->endmonth = 7;
        $this->assertSame(2027, configuration::season_year($config, strtotime('2027-06-15 UTC')));
        $config->startmonth = 12;
        $config->endmonth = 1;
        $config->scheduled = 0;
        $this->assertSame(2027, configuration::season_year($config, strtotime('2027-01-02 UTC')));
    }

    /**
     * Test single-day, normal-range and leap-year date clamping.
     */
    public function test_other_seasons(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->timezone = 'UTC';
        $config = configuration::get();
        $config->startmonth = $config->endmonth = 2;
        $config->startday = $config->endday = 29;
        $this->assertTrue(configuration::in_season($config, strtotime('2027-02-28 UTC')));
        $this->assertFalse(configuration::in_season($config, strtotime('2028-02-28 UTC')));
        $this->assertTrue(configuration::in_season($config, strtotime('2028-02-29 UTC')));
        $config->startmonth = 5;
        $config->endmonth = 7;
        $config->startday = $config->endday = 10;
        $this->assertTrue(configuration::in_season($config, strtotime('2027-06-15 UTC')));
        $this->assertFalse(configuration::in_season($config, strtotime('2027-08-15 UTC')));
        $config->startmonth = 13;
        $this->assertFalse(configuration::in_season($config, time()));
        $config->scheduled = 0;
        $this->assertTrue(configuration::in_season($config, time()));
    }

    /**
     * Verify bounded configuration and the disabled-by-default state.
     */
    public function test_configuration_bounds(): void {
        $this->resetAfterTest();
        $this->assertEmpty(configuration::get()->enabled);
        set_config('idledelay', -100, 'local_eledia_xmas');
        set_config('intensity', 9000, 'local_eledia_xmas');
        $this->assertSame(15, configuration::get()->idledelay);
        $this->assertSame(1.5, configuration::get()->intensity);
    }

    /**
     * Verify activity pages, guests and editing sessions are never decorated.
     */
    public function test_page_eligibility(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $config = configuration::get();
        $config->enabled = 1;
        $config->scheduled = 0;
        $config->pages .= ',course';
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url('/my/index.php');
        $page->set_pagelayout('mydashboard');
        $page->set_pagetype('my-index');
        $this->assertTrue(configuration::allowed($page, $config, time()));
        foreach (['mod-quiz-attempt', 'mod-forum-view', 'admin-setting-local_eledia_xmas', 'login-index'] as $type) {
            $page->set_pagetype($type);
            $this->assertFalse(configuration::allowed($page, $config, time()));
        }
        $page->set_pagetype('course-view-topics');
        $page->set_pagelayout('course');
        $this->assertTrue(configuration::allowed($page, $config, time()));
        $USER->editing = true;
        $this->assertFalse(configuration::allowed($page, $config, time()));
        $USER->editing = false;
        $this->setGuestUser();
        $this->assertFalse(configuration::allowed($page, $config, time()));
        $this->setUser(null);
        $this->assertFalse(configuration::allowed($page, $config, time()));
    }
}
