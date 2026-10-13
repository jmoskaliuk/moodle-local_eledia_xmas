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
 * Verify output and administrator-only preview bypass.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eledia_xmas\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Check that the preview bypasses disabled settings only for administrators.
     */
    public function test_preview_requires_admin(): void {
        global $PAGE, $SESSION, $USER;
        $this->resetAfterTest();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('frontpage');
        $PAGE->set_pagetype('site-index');
        $this->setUser($this->getDataGenerator()->create_user());
        $SESSION->local_eledia_xmas_preview = ['userid' => (int)$USER->id, 'until' => time() + HOURSECS];
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_footer($hook);
        $this->assertSame('', $hook->get_output());
        $this->setAdminUser();
        $SESSION->local_eledia_xmas_preview = ['userid' => (int)$USER->id, 'until' => time() + HOURSECS];
        hook_callbacks::before_footer($hook);
        $this->assertStringContainsString('id="local-eledia-xmas"', $hook->get_output());
        $this->assertStringContainsString('data-action="preview"', $hook->get_output());
    }

    /**
     * Expired previews and non-overview pages stay undecorated.
     */
    public function test_preview_expiry_and_excluded_pages(): void {
        global $PAGE, $SESSION, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/my/');
        $PAGE->set_pagelayout('mydashboard');
        $PAGE->set_pagetype('my-index');
        $SESSION->local_eledia_xmas_preview = ['userid' => (int)$USER->id, 'until' => time() - 1];
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_footer($hook);
        $this->assertSame('', $hook->get_output());
        $SESSION->local_eledia_xmas_preview['until'] = time() + HOURSECS;
        $PAGE->set_pagetype('mod-quiz-attempt');
        hook_callbacks::before_footer($hook);
        $this->assertSame('', $hook->get_output());
    }

    /**
     * Verify disabled output and redirect pages do not inject assets.
     */
    public function test_disabled_and_unset_url(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $hook = new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_footer($hook);
        $this->assertDebuggingNotCalled();
        $this->assertSame('', $hook->get_output());
        $PAGE->set_url('/my/index.php');
        $PAGE->set_pagelayout('mydashboard');
        $PAGE->set_pagetype('my-index');
        hook_callbacks::before_footer($hook);
        $this->assertSame('', $hook->get_output());
    }

    /**
     * Quiz rewards require a visible, final grade and a meaningful pass threshold.
     */
    public function test_quiz_pass_states(): void {
        global $PAGE, $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'grade' => 10]);
        $item = $DB->get_record('grade_items', ['itemmodule' => 'quiz', 'iteminstance' => $quiz->id], '*', MUST_EXIST);
        $DB->set_field('grade_items', 'gradepass', 5, ['id' => $item->id]);
        $DB->set_field('grade_items', 'needsupdate', 0, ['id' => $item->id]);
        $this->setUser($student);
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-topics');
        $this->assertSame([$quiz->cmid => false], hook_callbacks::quiz_states($course->id, $student->id));
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $student->id], false);
        $grade->finalgrade = 7;
        $grade->insert();
        $this->assertSame([$quiz->cmid => true], hook_callbacks::quiz_states($course->id, $student->id));
        $DB->set_field('grade_grades', 'hidden', 1, ['id' => $grade->id]);
        $this->assertSame([], hook_callbacks::quiz_states($course->id, $student->id));
        $DB->set_field('grade_grades', 'hidden', 0, ['id' => $grade->id]);
        $DB->set_field('grade_items', 'gradepass', 0, ['id' => $item->id]);
        $this->assertSame([], hook_callbacks::quiz_states($course->id, $student->id));
        $DB->set_field('grade_items', 'gradepass', 5, ['id' => $item->id]);
        $PAGE->set_pagetype('mod-quiz-attempt');
        $this->assertSame([], hook_callbacks::quiz_states($course->id, $student->id));
    }
}
