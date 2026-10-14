<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

namespace block_tarsus\output;

use completion_info;
use context_course;
use mod_tarsuszero\local\scoring;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * What the block shows on a course page: the learner's standing in every
 * Tarsus activity they can open, where to continue, and — for teachers —
 * how the course is doing per activity.
 *
 * Everything comes from Moodle's own tables (the figures the activity
 * stores, the gradebook, completion); the block never calls the brain.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview implements renderable, templatable {

    /** @var int The course. */
    private int $courseid;

    /** @var int The viewer. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $courseid The course.
     * @param int $userid   The viewer.
     */
    public function __construct(int $courseid, int $userid) {
        $this->courseid = $courseid;
        $this->userid = $userid;
    }

    /**
     * The Tarsus activities the viewer may open in this course.
     *
     * @return \cm_info[]
     */
    public function activities(): array {
        $modinfo = get_fast_modinfo($this->courseid, $this->userid);
        $cms = [];
        foreach ($modinfo->get_instances_of('tarsuszero') as $cm) {
            if ($cm->uservisible && $cm->deletioninprogress == 0) {
                $cms[] = $cm;
            }
        }
        return $cms;
    }

    /**
     * Whether there is anything to show at all.
     *
     * @return bool
     */
    public function has_content(): bool {
        return $this->activities() !== [];
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $course = get_course($this->courseid);
        $completion = new completion_info($course);
        $teacher = has_capability('block/tarsus:viewsummary', context_course::instance($this->courseid), $this->userid);

        $rows = [];
        $summary = [];
        $continue = null;
        foreach ($this->activities() as $cm) {
            $activity = $DB->get_record('tarsuszero', ['id' => $cm->instance], '*', MUST_EXIST);
            $figures = scoring::row((int) $activity->id, $this->userid);
            $progress = scoring::progress($activity, $figures);
            $grade = scoring::grade($activity, $figures);
            $state = $completion->is_enabled($cm)
                ? (int) $completion->get_data($cm, false, $this->userid)->completionstate : null;
            $hastest = \mod_tarsuszero\local\didactics::mode_enabled($activity, 'test');
            $hasbuild = \mod_tarsuszero\local\didactics::mode_enabled($activity, 'build');

            $rows[] = [
                'name' => format_string($cm->name),
                'url' => (new moodle_url('/mod/tarsuszero/view.php', ['id' => $cm->id]))->out(false),
                'progress' => round($progress),
                'hasfigures' => $figures !== null,
                'hastest' => $hastest,
                'hasbuild' => $hasbuild,
                'xp' => $figures ? round((float) $figures->earnedxp, 1) : 0,
                'totalxp' => $figures ? round((float) $figures->totalxp, 1) : 0,
                'units' => $figures ? round((float) $figures->earnedunits, 1) : 0,
                'totalunits' => $figures ? round((float) $figures->totalunits, 1) : 0,
                'hasgrade' => $grade !== null,
                'grade' => $grade !== null ? round($grade) : 0,
                'trackscompletion' => $state !== null,
                'completed' => in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
            ];

            // Where to continue: the most recently touched activity still open.
            if ($progress < 100 && ($hastest || $hasbuild)) {
                $touched = $figures ? (int) $figures->timemodified : 0;
                if ($continue === null || $touched > $continue['touched']) {
                    $continue = ['name' => format_string($cm->name), 'url' => end($rows)['url'], 'touched' => $touched];
                }
            }

            if ($teacher) {
                $summary[] = $this->summary_row($activity, $cm);
            }
        }

        return [
            'activities' => $rows,
            'hascontinue' => $continue !== null,
            'continue' => $continue,
            'isteacher' => $teacher,
            'summary' => $summary,
        ];
    }

    /**
     * The teacher's figures for one activity.
     *
     * @param \stdClass $activity The tarsuszero record.
     * @param \cm_info  $cm       Its course module.
     * @return array<string, mixed>
     */
    private function summary_row(\stdClass $activity, \cm_info $cm): array {
        global $DB;

        $figures = $DB->get_records('tarsuszero_progress', ['tarsuszeroid' => $activity->id]);
        $progresses = [];
        $grades = [];
        foreach ($figures as $row) {
            $progresses[] = scoring::progress($activity, $row);
            $grade = scoring::grade($activity, $row);
            if ($grade !== null) {
                $grades[] = $grade;
            }
        }
        [$insql, $params] = $DB->get_in_or_equal([COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], SQL_PARAMS_NAMED);
        $params['cmid'] = $cm->id;
        $completed = $DB->count_records_select('course_modules_completion', "coursemoduleid = :cmid AND completionstate $insql", $params);

        return [
            'name' => format_string($cm->name),
            'url' => (new moodle_url('/mod/tarsuszero/view.php', ['id' => $cm->id]))->out(false),
            'started' => count($figures),
            'avgprogress' => $progresses ? round(array_sum($progresses) / count($progresses)) : 0,
            'hasgrades' => $grades !== [],
            'avggrade' => $grades ? round(array_sum($grades) / count($grades)) : 0,
            'completed' => $completed,
        ];
    }
}
