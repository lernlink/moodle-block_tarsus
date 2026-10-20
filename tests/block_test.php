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


namespace block_tarsus;

use mod_tarsuszero\local\scoring;

/**
 * The block's content on a course page.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_tarsus
 * @covers     \block_tarsus\output\overview
 */
final class block_test extends \advanced_testcase {

    /**
     * A block instance on a course page, as the given user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user   The viewer.
     * @return \block_tarsus
     */
    private function block_for(\stdClass $course, \stdClass $user): \block_tarsus {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/tarsus/block_tarsus.php');
        $this->setUser($user);
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_url('/course/view.php', ['id' => $course->id]);
        $block = new \block_tarsus();
        $block->page = $page;
        $block->init();
        return $block;
    }

    /**
     * A course with a Tarsus activity, a student and a teacher.
     *
     * @return array{course: \stdClass, tz: \stdClass, activity: \stdClass, student: \stdClass, teacher: \stdClass}
     */
    private function setup_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $tz = $this->getDataGenerator()->create_module('tarsuszero', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionprogress' => 50]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        return ['course' => $course, 'tz' => $tz, 'activity' => $DB->get_record('tarsuszero', ['id' => $tz->id]),
            'student' => $student, 'teacher' => $teacher];
    }

    /**
     * A course without a Tarsus activity leaves the block empty, so it hides.
     */
    public function test_empty_without_activities(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $content = $this->block_for($course, $student)->get_content();

        $this->assertSame('', $content->text);
    }

    /**
     * A learner sees their standing, the figures and where to continue.
     */
    public function test_learner_sees_standing_and_continue(): void {
        $this->resetAfterTest(true);
        $data = $this->setup_course();
        scoring::record($data['activity'], (int) $data['student']->id, [
            'earnedxp' => 3, 'totalxp' => 9, 'earnedunits' => 9, 'totalunits' => 9,
            'practiceprogress' => 40, 'learnprogress' => 100, 'progress' => 70,
        ]);

        $content = $this->block_for($data['course'], $data['student'])->get_content();

        $this->assertStringContainsString(get_string('continue_in', 'block_tarsus', $data['tz']->name), $content->text);
        $this->assertStringContainsString('70%', $content->text);
        $this->assertStringContainsString('3 / 9 ' . get_string('xp', 'block_tarsus'), $content->text);
        $this->assertStringContainsString('9 / 9 ' . get_string('lu', 'block_tarsus'), $content->text);
        $this->assertStringContainsString('style="width: 33%"', $content->text);
        $this->assertStringContainsString('style="width: 100%"', $content->text);
        $this->assertStringContainsString(get_string('grade_chip', 'block_tarsus', 67), $content->text);
        $this->assertStringContainsString(get_string('completed', 'block_tarsus'), $content->text);
        $this->assertStringContainsString(get_string('completed_of', 'block_tarsus', (object) ['done' => 1, 'total' => 1]),
            $content->text);
        $this->assertStringNotContainsString(get_string('coursesummary', 'block_tarsus'), $content->text);
    }

    /**
     * Before the first visit there are no figures to show.
     */
    public function test_not_started_shows_no_figures(): void {
        $this->resetAfterTest(true);
        $data = $this->setup_course();

        $content = $this->block_for($data['course'], $data['student'])->get_content();

        $this->assertStringContainsString(get_string('notstarted', 'block_tarsus'), $content->text);
        $this->assertStringNotContainsString(get_string('xp', 'block_tarsus'), $content->text);
        $this->assertStringNotContainsString('block_tarsus__chip', $content->text);
        $this->assertStringContainsString('block_tarsus__continue', $content->text);
    }

    /**
     * Once an activity is complete there is nothing to continue.
     */
    public function test_no_continue_when_everything_is_done(): void {
        $this->resetAfterTest(true);
        $data = $this->setup_course();
        scoring::record($data['activity'], (int) $data['student']->id, [
            'earnedxp' => 9, 'totalxp' => 9, 'earnedunits' => 9, 'totalunits' => 9,
            'practiceprogress' => 100, 'learnprogress' => 100, 'progress' => 100,
        ]);

        $content = $this->block_for($data['course'], $data['student'])->get_content();

        $this->assertStringNotContainsString('block_tarsus__continue', $content->text);
    }

    /**
     * A teacher gets the course summary, and nothing else.
     */
    public function test_teacher_sees_summary(): void {
        $this->resetAfterTest(true);
        $data = $this->setup_course();
        scoring::record($data['activity'], (int) $data['student']->id, [
            'earnedxp' => 3, 'totalxp' => 9, 'earnedunits' => 9, 'totalunits' => 9,
            'practiceprogress' => 40, 'learnprogress' => 100, 'progress' => 70,
        ]);

        $content = $this->block_for($data['course'], $data['teacher'])->get_content();

        $this->assertStringContainsString(get_string('coursesummary', 'block_tarsus'), $content->text);
        $this->assertStringContainsString(get_string('n_learners', 'block_tarsus', 1), $content->text);
        // No learner elements: teachers earn no progress of their own.
        $this->assertStringNotContainsString('block_tarsus__continue', $content->text);
        $this->assertStringNotContainsString(get_string('courseprogress', 'block_tarsus'), $content->text);
        $this->assertStringNotContainsString('block_tarsus__activity', $content->text);
        $this->assertStringContainsString(get_string('n_completed', 'block_tarsus', 1), $content->text);
        $this->assertStringContainsString(get_string('n_inprogress', 'block_tarsus', 0), $content->text);
        $this->assertStringContainsString(get_string('n_notstarted', 'block_tarsus', 0), $content->text);
        $this->assertStringContainsString(get_string('avggrade_chip', 'block_tarsus', 67), $content->text);
        $this->assertStringContainsString('title="50–75: 1"', $content->text);
    }

    /**
     * The teacher's stacked bar counts every enrolled student, including
     * those who never opened the activity.
     */
    public function test_summary_counts_all_students(): void {
        $this->resetAfterTest(true);
        $data = $this->setup_course();
        $second = $this->getDataGenerator()->create_user();
        $third = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($second->id, $data['course']->id, 'student');
        $this->getDataGenerator()->enrol_user($third->id, $data['course']->id, 'student');
        scoring::record($data['activity'], (int) $data['student']->id, [
            'earnedxp' => 9, 'totalxp' => 9, 'earnedunits' => 9, 'totalunits' => 9,
            'practiceprogress' => 100, 'learnprogress' => 100, 'progress' => 100,
        ]);
        scoring::record($data['activity'], (int) $second->id, [
            'earnedxp' => 1, 'totalxp' => 9, 'earnedunits' => 0, 'totalunits' => 9,
            'practiceprogress' => 10, 'learnprogress' => 0, 'progress' => 10,
        ]);
        // The teacher's own try must not count.
        scoring::record($data['activity'], (int) $data['teacher']->id, [
            'earnedxp' => 4, 'totalxp' => 9, 'earnedunits' => 4, 'totalunits' => 9,
            'practiceprogress' => 44, 'learnprogress' => 44, 'progress' => 44,
        ]);

        $content = $this->block_for($data['course'], $data['teacher'])->get_content();

        $this->assertStringContainsString(get_string('n_completed', 'block_tarsus', 1), $content->text);
        $this->assertStringContainsString(get_string('n_inprogress', 'block_tarsus', 1), $content->text);
        $this->assertStringContainsString(get_string('n_notstarted', 'block_tarsus', 1), $content->text);
        $this->assertStringContainsString('title=" 0–25: 1"', $content->text);
        $this->assertStringContainsString('title="25–50: 0"', $content->text);
        $this->assertStringContainsString('title="75–100: 1"', $content->text);
    }

    /**
     * Ring offsets and bars are computed as on the stack card.
     */
    public function test_ring_and_bar_geometry(): void {
        $this->assertSame('56.55', \block_tarsus\output\overview::ring_offset(0));
        $this->assertSame('0.00', \block_tarsus\output\overview::ring_offset(100));
        $this->assertSame('28.28', \block_tarsus\output\overview::ring_offset(50));

        $bar = \block_tarsus\output\overview::bar(2.5, 4, 'test', 'XP');
        $this->assertSame('2.5', $bar['earned']);
        $this->assertSame('4', $bar['total']);
        $this->assertSame(63, $bar['percent']);

        $bar = \block_tarsus\output\overview::bar(30, 36, 'build', 'LU');
        $this->assertSame(83, $bar['percent']);
        $this->assertSame(0, \block_tarsus\output\overview::bar(1, 0, 'test', 'XP')['percent']);
    }

    /**
     * The block belongs on course pages only.
     */
    public function test_block_is_course_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/tarsus/block_tarsus.php');
        $formats = (new \block_tarsus())->applicable_formats();
        $this->assertTrue($formats['course-view']);
        $this->assertFalse($formats['my']);
        $this->assertFalse($formats['site']);
    }
}
