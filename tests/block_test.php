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

/**
 * Tests for the block_tarsus scaffold.
 *
 * @package    block_tarsus
 * @covers     \block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_test extends \advanced_testcase {

    /**
     * The block initialises its title and returns placeholder content.
     *
     * @return void
     */
    public function test_block_provides_content(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/tarsus/block_tarsus.php');

        $this->resetAfterTest(true);

        $block = new \block_tarsus();
        $block->init();

        $this->assertSame(get_string('pluginname', 'block_tarsus'), $block->title);

        $content = $block->get_content();
        $this->assertNotEmpty($content->text);
        $this->assertSame(get_string('placeholder', 'block_tarsus'), $content->text);
    }

    /**
     * The block is only applicable on course views.
     *
     * @return void
     */
    public function test_block_is_course_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/tarsus/block_tarsus.php');

        $block = new \block_tarsus();
        $formats = $block->applicable_formats();

        $this->assertTrue($formats['course-view']);
        $this->assertFalse($formats['my']);
        $this->assertFalse($formats['site']);
    }
}
