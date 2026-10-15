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
use mod_tarsuszero\local\didactics;
use mod_tarsuszero\local\scoring;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * What the block shows on a course page: the learner's standing in every
 * Tarsus activity they can open and where to continue; for teachers, who
 * earn no progress of their own, how the course is doing per activity
 * instead.
 *
 * Everything comes from Moodle's own tables (the figures the activity
 * stores, the gradebook, completion); the block never calls the brain.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview implements renderable, templatable {

    /** @var float Circumference of the ring's circle (r=9 in a 24 viewBox), as in the stack card. */
    public const RING = 56.55;

    /** @var int Up to this many units a bar is drawn as one segment per unit. */
    public const MAX_SEGMENTS = 20;

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
        $context = context_course::instance($this->courseid);
        $teacher = has_capability('block/tarsus:viewsummary', $context, $this->userid);

        $rows = [];
        $summary = [];
        $continue = null;
        $sum = 0.0;
        $done = 0;
        foreach ($this->activities() as $cm) {
            $activity = $DB->get_record('tarsuszero', ['id' => $cm->instance], '*', MUST_EXIST);
            if ($teacher) {
                // Teachers earn no progress of their own: the summary is all they get.
                $summary[] = $this->summary_row($activity, $cm, $completion);
                continue;
            }
            $figures = scoring::row((int) $activity->id, $this->userid);
            $progress = scoring::progress($activity, $figures);
            $grade = scoring::grade($activity, $figures);
            $tracked = $completion->is_enabled($cm) > 0;
            $completed = $tracked
                ? self::is_complete((int) $completion->get_data($cm, false, $this->userid)->completionstate)
                : $progress >= 100;
            $hastest = didactics::mode_enabled($activity, 'test');
            $hasbuild = didactics::mode_enabled($activity, 'build');
            $url = (new moodle_url('/mod/tarsuszero/view.php', ['id' => $cm->id]))->out(false);

            $rows[] = [
                'name' => format_string($cm->name),
                'url' => $url,
                'progress' => (int) round($progress),
                'ringoffset' => self::ring_offset($progress),
                'size' => 36,
                'ringlabel' => get_string('progress', 'block_tarsus'),
                'hasfigures' => $figures !== null,
                'hastest' => $hastest,
                'hasbuild' => $hasbuild,
                'xp' => $figures ? self::bar((float) $figures->earnedxp, (float) $figures->totalxp, 'test',
                    get_string('xp', 'block_tarsus')) : null,
                'lu' => $figures ? self::bar((float) $figures->earnedunits, (float) $figures->totalunits, 'build',
                    get_string('lu', 'block_tarsus')) : null,
                'hasgrade' => $figures !== null && $grade !== null,
                'grade' => $grade !== null ? (int) round($grade) : 0,
                'trackscompletion' => $tracked,
                'completed' => $completed,
            ];
            $sum += $progress;
            $done += $completed ? 1 : 0;

            // Where to continue: the most recently touched activity still open.
            if ($progress < 100 && ($hastest || $hasbuild)) {
                $touched = $figures ? (int) $figures->timemodified : 0;
                if ($continue === null || $touched > $continue['touched']) {
                    $continue = ['name' => format_string($cm->name), 'url' => $url, 'touched' => $touched];
                }
            }

        }

        $average = $rows ? $sum / count($rows) : 0.0;
        return [
            'course' => [
                'progress' => (int) round($average),
                'ringoffset' => self::ring_offset($average),
                'size' => 64,
                'ringlabel' => get_string('courseprogress', 'block_tarsus'),
                'done' => $done,
                'total' => count($rows),
            ],
            'activities' => $rows,
            'hascontinue' => $continue !== null,
            'continue' => $continue,
            'isteacher' => $teacher,
            'summary' => $summary,
        ];
    }

    /**
     * The stroke offset that fills a ring up to a percentage.
     *
     * @param float $percent 0..100.
     * @return string Offset along the circumference, two decimals.
     */
    public static function ring_offset(float $percent): string {
        $percent = max(0.0, min(100.0, $percent));
        return number_format(self::RING * (1 - $percent / 100), 2, '.', '');
    }

    /**
     * A bar for earned-of-total figures: one segment per unit while the
     * total is small, a plain percentage bar otherwise.
     *
     * @param float  $earned
     * @param float  $total
     * @param string $kind "test" or "build"; colours the bar.
     * @param string $unit The unit's label (XP, LU).
     * @return array{kind: string, unit: string, earned: string, total: string, percent: int, segmented: bool, segments: array}
     */
    public static function bar(float $earned, float $total, string $kind, string $unit): array {
        $percent = $total > 0 ? (int) round(min(100.0, max(0.0, $earned / $total * 100))) : 0;
        $segmented = $total > 0 && $total <= self::MAX_SEGMENTS && abs($total - round($total)) < 0.001;
        $segments = [];
        if ($segmented) {
            $filled = (int) round($earned);
            for ($i = 1; $i <= (int) round($total); $i++) {
                $segments[] = ['filled' => $i <= $filled];
            }
        }
        return [
            'kind' => $kind,
            'unit' => $unit,
            'earned' => self::figure($earned),
            'total' => self::figure($total),
            'percent' => $percent,
            'segmented' => $segmented,
            'segments' => $segments,
        ];
    }

    /**
     * A figure without a trailing ".0".
     *
     * @param float $value
     * @return string
     */
    private static function figure(float $value): string {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /**
     * Whether a completion state counts as complete.
     *
     * @param int $state A COMPLETION_* constant.
     * @return bool
     */
    private static function is_complete(int $state): bool {
        return in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * The learners the teacher's summary is about: enrolled users holding a
     * gradebook role (students), active enrolments only.
     *
     * @return int[] User ids.
     */
    private function students(): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('intval', explode(',', (string) $CFG->gradebookroles)));
        if (!$roleids) {
            return [];
        }
        $context = context_course::instance($this->courseid);
        [$enrolledsql, $params] = get_enrolled_sql($context, '', 0, true);
        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $params += $roleparams;
        $params['ctx'] = $context->id;
        $sql = "SELECT DISTINCT eu.id
                  FROM ($enrolledsql) eu
                  JOIN {role_assignments} ra ON ra.userid = eu.id AND ra.contextid = :ctx AND ra.roleid $rolesql";
        return array_map('intval', array_keys($DB->get_records_sql($sql, $params)));
    }

    /**
     * The teacher's figures for one activity.
     *
     * @param \stdClass       $activity   The tarsuszero record.
     * @param \cm_info        $cm         Its course module.
     * @param completion_info $completion The course's completion.
     * @return array<string, mixed>
     */
    private function summary_row(\stdClass $activity, \cm_info $cm, completion_info $completion): array {
        global $DB;

        // Only students count; teachers trying the activity do not skew the figures.
        $students = array_flip($this->students());
        $figures = [];
        foreach ($DB->get_records('tarsuszero_progress', ['tarsuszeroid' => $activity->id]) as $row) {
            if (isset($students[(int) $row->userid])) {
                $figures[(int) $row->userid] = $row;
            }
        }
        $tracked = $completion->is_enabled($cm) > 0;
        $completedby = [];
        if ($tracked) {
            [$insql, $params] = $DB->get_in_or_equal([COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], SQL_PARAMS_NAMED);
            $params['cmid'] = $cm->id;
            $completedby = $DB->get_fieldset_select('course_modules_completion', 'userid',
                "coursemoduleid = :cmid AND completionstate $insql", $params);
            $completedby = array_values(array_filter(array_map('intval', $completedby),
                fn(int $userid): bool => isset($students[$userid])));
        }

        $progresses = [];
        $grades = [];
        $buckets = [0, 0, 0, 0];
        $completed = 0;
        $inprogress = 0;
        foreach ($figures as $row) {
            $progress = scoring::progress($activity, $row);
            $progresses[] = $progress;
            $grade = scoring::grade($activity, $row);
            if ($grade !== null) {
                $grades[] = $grade;
            }
            $buckets[min(3, (int) floor($progress / 25))]++;
            $iscomplete = $tracked ? in_array((int) $row->userid, $completedby, true) : $progress >= 100;
            if ($iscomplete) {
                $completed++;
            } else {
                $inprogress++;
            }
        }
        // Learners who completed without ever storing figures (e.g. manual completion) still count.
        foreach ($completedby as $userid) {
            if (!isset($figures[$userid])) {
                $completed++;
            }
        }
        $total = count($students);
        $notstarted = $total - $completed - $inprogress;
        $peak = max(1, max($buckets));
        $avgprogress = $progresses ? array_sum($progresses) / count($progresses) : 0.0;

        return [
            'name' => format_string($cm->name),
            'url' => (new moodle_url('/mod/tarsuszero/view.php', ['id' => $cm->id]))->out(false),
            'total' => $total,
            'completed' => $completed,
            'inprogress' => $inprogress,
            'notstarted' => $notstarted,
            'completedpct' => $total ? round($completed / $total * 100, 1) : 0,
            'inprogresspct' => $total ? round($inprogress / $total * 100, 1) : 0,
            'notstartedpct' => $total ? round($notstarted / $total * 100, 1) : 0,
            'progress' => (int) round($avgprogress),
            'ringoffset' => self::ring_offset($avgprogress),
            'size' => 32,
            'ringlabel' => get_string('avgprogress', 'block_tarsus'),
            'hasgrades' => $grades !== [],
            'avggrade' => $grades ? (int) round(array_sum($grades) / count($grades)) : 0,
            'hashistogram' => $progresses !== [],
            'buckets' => array_map(fn(int $i, int $count): array => [
                'label' => [' 0–25', '25–50', '50–75', '75–100'][$i],
                'count' => $count,
                'height' => (int) round($count / $peak * 100),
            ], array_keys($buckets), $buckets),
        ];
    }
}
