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
 * Render the seasonal overlay.
 *
 * @package    local_eledia_xmas
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eledia_xmas;

/**
 * Output callbacks shared by Moodle 4.5 and 5.2.
 */
class hook_callbacks {
    /**
     * Add decoration only to eligible pages or an authorised administrator preview.
     *
     * @param \core\hook\output\before_footer_html_generation $hook Output hook.
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $OUTPUT, $CFG, $SESSION, $USER;
        if (!$PAGE->has_set_url()) {
            return;
        }
        $config = configuration::get();
        $preview = !empty($SESSION->local_eledia_xmas_preview)
            && $SESSION->local_eledia_xmas_preview['userid'] === (int)$USER->id
            && $SESSION->local_eledia_xmas_preview['until'] > time()
            && has_capability('moodle/site:config', \context_system::instance());
        $eligibility = clone $config;
        if ($preview) {
            $eligibility->enabled = 1;
            $eligibility->scheduled = 0;
            $eligibility->pages = 'site-index,my-index,my-courses,course';
        }
        if (!configuration::allowed($PAGE, $eligibility, time())) {
            return;
        }
        $strings = [];
        foreach (
            ['snowon', 'snowoff', 'quiet', 'falling', 'clearing', 'building', 'storm', 'reducedmotion',
                'soundon', 'soundoff', 'celebrateactivity', 'celebratequiz',
                'snowhelpbrrr', 'snowhelpblanket', 'snowhelpwinter', 'celebratecourse', 'celebratediscovery'] as $key
        ) {
            $strings[$key] = get_string($key, 'local_eledia_xmas');
        }
        $hook->add_html($OUTPUT->render_from_template('local_eledia_xmas/overlay', [
            'preview' => $preview,
            'stopurl' => (new \moodle_url('/local/eledia_xmas/preview.php', [
                'action' => 'stop', 'sesskey' => sesskey(),
            ]))->out(false),
            'homeurl' => (new \moodle_url('/?redirect=0'))->out(false),
            'dashboardurl' => (new \moodle_url('/my/'))->out(false),
            'coursesurl' => (new \moodle_url('/my/courses.php'))->out(false),
            // Bulky page data travels as a data attribute; js_call_amd arguments must stay below 1024 characters.
            'pagedata' => json_encode([
                'quizstates' => self::quiz_states($PAGE->course->id, $USER->id),
                'completionstates' => self::completion_states($PAGE->course, $USER->id),
                'audio' => self::audio_urls(),
                'strings' => $strings,
            ]),
        ]));
        $PAGE->requires->js_call_amd('local_eledia_xmas/snow', 'init', [[
            'preview' => $preview,
            'idledelay' => $config->idledelay * 1000,
            'intensity' => $config->intensity,
            'contrast' => $config->contrast,
            'fullcover' => !empty($config->fullcover),
            'ledges' => !empty($config->ledges),
            'eastereggs' => !empty($config->eastereggs),
            'storagekey' => 'local_eledia_xmas:' . sha1($CFG->wwwroot . ':' . $USER->id),
            'seasonyear' => configuration::season_year($config, time()),
        ]]);
    }

    /**
     * Same-origin audio only; no request to the generation service at runtime.
     * @return array Available sound URLs and the exact matching greeting text.
     */
    public static function audio_urls(): array {
        $audio = [];
        foreach (['activity', 'quiz', 'course', 'discovery'] as $type) {
            $file = '/audio/' . $type . '.mp3';
            if (is_file(__DIR__ . '/..' . $file)) {
                $audio[$type] = (new \moodle_url('/local/eledia_xmas' . $file))->out(false);
            }
        }
        $language = explode('_', current_language())[0];
        // If language customisation changes a greeting, use the local voice instead of stale recorded wording.
        $manifest = __DIR__ . '/../audio/transcripts.json';
        $transcripts = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : [];
        foreach (['star' => 'santagreeting', 'bauble' => 'reindeergreeting', 'snowball' => 'snowmangreeting'] as $key => $string) {
            $file = '/audio/' . $language . '/' . $key . '.mp3';
            $text = get_string($string, 'local_eledia_xmas');
            if (($transcripts[$language][$key] ?? null) === $text && is_file(__DIR__ . '/..' . $file)) {
                $audio[$key] = (new \moodle_url('/local/eledia_xmas' . $file))->out(false);
            }
        }
        return $audio;
    }

    /**
     * Read completion for visible automatic activities and the enrolled user's course.
     * @param \stdClass $course Current course.
     * @param int $userid Current user.
     * @return array Completion identifiers mapped to booleans.
     */
    public static function completion_states(\stdClass $course, int $userid): array {
        global $PAGE, $CFG, $DB;
        if ($course->id == SITEID || !str_starts_with($PAGE->pagetype, 'course-view-')) {
            return [];
        }
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        $context = \context_course::instance($course->id);
        if (!$completion->is_enabled() || !is_enrolled($context, $userid, '', true)) {
            return [];
        }
        // Check the user whose data is read, so the guard stays correct if another user is passed.
        $canviewgrades = has_capability('moodle/grade:view', $context, $userid);
        $visiblegrades = [];
        $sql = "SELECT gi.id, gi.itemmodule, gi.iteminstance, gi.itemnumber
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gi.needsupdate = 0
                   AND (gi.hidden = 0 OR (gi.hidden > 1 AND gi.hidden < :itemnow))
                   AND (gg.hidden IS NULL OR gg.hidden = 0 OR (gg.hidden > 1 AND gg.hidden < :gradenow))";
        foreach (
            $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $course->id,
                'itemnow' => time(), 'gradenow' => time()]) as $grade
        ) {
            $visiblegrades[$grade->itemmodule . ':' . $grade->iteminstance . ':' . $grade->itemnumber] = true;
        }
        $states = [];
        foreach (get_fast_modinfo($course->id, $userid)->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
                continue;
            }
            $data = $completion->get_data($cm, true, $userid);
            // Do not expose a hidden grade through the celebration. Failed completion is not a success.
            if (
                $cm->completiongradeitemnumber !== null && (!$canviewgrades
                    || empty($visiblegrades[$cm->modname . ':' . $cm->instance . ':' . $cm->completiongradeitemnumber]))
            ) {
                continue;
            }
            $states['activity:' . $cm->id] = in_array(
                (int)$data->completionstate,
                [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                true
            );
        }
        $states['course:' . $course->id] = $completion->is_course_complete($userid);
        return $states;
    }

    /**
     * Read visible quiz pass states on a course overview; never expose marks or pending grades.
     *
     * @param int $courseid Course identifier.
     * @param int $userid Current user identifier.
     * @return array Quiz module identifiers mapped to pass states.
     */
    public static function quiz_states(int $courseid, int $userid): array {
        global $DB, $PAGE;
        if ($courseid == SITEID || !str_starts_with($PAGE->pagetype, 'course-view-')) {
            return [];
        }
        $context = \context_course::instance($courseid);
        if (!has_capability('moodle/grade:view', $context, $userid)) {
            return [];
        }
        $sql = "SELECT gi.iteminstance, gi.gradepass, gg.finalgrade, gg.hidden AS gradehidden
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                   AND gi.itemnumber = 0 AND gi.gradepass > gi.grademin AND gi.needsupdate = 0
                   AND (gi.hidden = 0 OR (gi.hidden > 1 AND gi.hidden < :now))";
        $grades = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid, 'now' => time()]);
        $states = [];
        foreach (get_fast_modinfo($courseid, $userid)->get_instances_of('quiz') as $cm) {
            $grade = $grades[$cm->instance] ?? null;
            if (!$cm->uservisible || !$grade || (int)$grade->gradehidden === 1 || (int)$grade->gradehidden > time()) {
                continue;
            }
            $states[$cm->id] = $grade->finalgrade !== null && $grade->finalgrade >= $grade->gradepass;
        }
        return $states;
    }
}
