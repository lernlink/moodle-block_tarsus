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
 * Library functions for block_tarsus.
 *
 * @package    block_tarsus
 * @copyright  2026 lern.link GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Map the block's icons onto Font Awesome.
 *
 * @return array<string, string>
 */
function block_tarsus_get_fontawesome_icon_map(): array {
    return [
        'block_tarsus:continue' => 'fa-solid fa-book-open',
        'block_tarsus:chevron' => 'fa-solid fa-chevron-right',
        'block_tarsus:grade' => 'fa-solid fa-graduation-cap',
        'block_tarsus:learners' => 'fa-solid fa-users',
    ];
}
