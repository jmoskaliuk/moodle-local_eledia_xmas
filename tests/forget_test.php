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
 * Verify the browser-data removal page is offered to the right users with the shared storage key.
 * @package local_eledia_xmas
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(configuration::class)]
final class forget_test extends \advanced_testcase {
    /**
     * The key is stable per user and site and separates users.
     */
    public function test_storage_key(): void {
        $this->assertSame(configuration::storage_key(5), configuration::storage_key(5));
        $this->assertNotSame(configuration::storage_key(5), configuration::storage_key(6));
        $this->assertStringStartsWith('local_eledia_xmas:', configuration::storage_key(5));
    }

    /**
     * Only the user themselves sees the link, and only while the plugin is enabled.
     */
    public function test_preferences_link(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/eledia_xmas/lib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $check = function (\stdClass $owner): bool {
            global $SITE;
            $node = new \navigation_node('useraccount');
            local_eledia_xmas_extend_navigation_user_settings(
                $node,
                $owner,
                \context_user::instance($owner->id),
                $SITE,
                \context_course::instance($SITE->id)
            );
            return (bool)$node->find('local_eledia_xmas_forget', \navigation_node::TYPE_SETTING);
        };
        $this->assertFalse($check($user));
        set_config('enabled', 1, 'local_eledia_xmas');
        $this->assertTrue($check($user));
        $this->assertFalse($check($other));
    }
}
