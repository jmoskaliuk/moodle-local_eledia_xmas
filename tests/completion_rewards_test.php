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
 * Verify completion rewards respect visibility, enrolment and successful states.
 * @package local_eledia_xmas
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class completion_rewards_test extends \advanced_testcase {
    /**
     * Check completion state boundaries without exposing hidden activities.
     */
    public function test_completion_states(): void {
        global $CFG, $PAGE, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $hidden = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'visible' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $manual = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->setUser($student);
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-topics');
        $states = hook_callbacks::completion_states($course, $student->id);
        $this->assertFalse($states['activity:' . $page->cmid]);
        $this->assertArrayNotHasKey('activity:' . $hidden->cmid, $states);
        $this->assertArrayNotHasKey('activity:' . $manual->cmid, $states);
        $this->assertFalse($states['course:' . $course->id]);
        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_id('page', $page->cmid);
        $completion->set_module_viewed($cm, $student->id);
        $this->assertTrue(hook_callbacks::completion_states($course, $student->id)['activity:' . $page->cmid]);
        $record = new \completion_completion(['userid' => $student->id, 'course' => $course->id]);
        $record->mark_complete();
        $this->assertTrue(hook_callbacks::completion_states($course, $student->id)['course:' . $course->id]);
        $DB->set_field(
            'course_modules_completion',
            'completionstate',
            COMPLETION_COMPLETE_FAIL,
            ['coursemoduleid' => $page->cmid, 'userid' => $student->id]
        );
        \cache::make('core', 'completion')->purge();
        $this->assertFalse(hook_callbacks::completion_states($course, $student->id)['activity:' . $page->cmid]);
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completiongradeitemnumber' => 0,
        ]);
        $item = $DB->get_record('grade_items', ['itemmodule' => 'quiz', 'iteminstance' => $quiz->id], '*', MUST_EXIST);
        $DB->set_field('grade_items', 'needsupdate', 0, ['id' => $item->id]);
        $this->assertArrayHasKey('activity:' . $quiz->cmid, hook_callbacks::completion_states($course, $student->id));
        $DB->set_field('grade_items', 'hidden', 1, ['id' => $item->id]);
        $this->assertArrayNotHasKey('activity:' . $quiz->cmid, hook_callbacks::completion_states($course, $student->id));
        $PAGE->set_pagetype('mod-quiz-attempt');
        $this->assertSame([], hook_callbacks::completion_states($course, $student->id));
        $PAGE->set_pagetype('course-view-topics');
        $outsider = $this->getDataGenerator()->create_user();
        $this->assertSame([], hook_callbacks::completion_states($course, $outsider->id));
    }
}
