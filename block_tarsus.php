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

/**
 * Tarsus Lerncoach course block.
 *
 * Surfaces student progress and quick access now; teacher dropout-risk alerts
 * via the Message API and live data follow later. Depends only on the
 * local_tarsus client interface.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The Tarsus Lerncoach block.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_tarsus extends block_base {

    /**
     * Initialise the block title.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_tarsus');
    }

    /**
     * Build the block content.
     *
     * @return stdClass The block content (text + footer).
     */
    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = get_string('placeholder', 'block_tarsus');
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Restrict the block to course views.
     *
     * @return array<string, bool> Format availability map.
     */
    public function applicable_formats(): array {
        return [
            'course-view' => true,
            'site'        => false,
            'my'          => false,
        ];
    }

    /**
     * The block has no global configuration.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }
}
