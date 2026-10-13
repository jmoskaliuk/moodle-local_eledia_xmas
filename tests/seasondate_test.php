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
 * Verify recurring date validation and preservation of existing settings.
 * @package local_eledia_xmas
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(admin_setting_seasondate::class)]
final class seasondate_test extends \advanced_testcase {
    /**
     * Verify legacy keys, leap day and rejection before either key is changed.
     */
    public function test_grouped_date(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $setting = new admin_setting_seasondate('start', 'Start', '', ['day' => 1, 'month' => 12]);
        set_config('startday', 1, 'local_eledia_xmas');
        set_config('startmonth', 12, 'local_eledia_xmas');
        $this->assertEquals(['day' => 1, 'month' => 12], $setting->get_setting());
        $this->assertSame('', $setting->write_setting(['day' => 29, 'month' => 2]));
        $this->assertEquals(['day' => 29, 'month' => 2], $setting->get_setting());
        foreach (
            [['day' => 31, 'month' => 4], ['day' => 0, 'month' => 12],
                ['day' => '1.5', 'month' => 12], ['day' => 1], 'invalid'] as $invalid
        ) {
            $this->assertNotSame('', $setting->write_setting($invalid));
            $this->assertEquals(['day' => 29, 'month' => 2], $setting->get_setting());
        }
        $html = $setting->output_html($setting->get_setting());
        $this->assertStringContainsString('February', $html);
        $this->assertStringContainsString('role="group"', $html);
    }
}
