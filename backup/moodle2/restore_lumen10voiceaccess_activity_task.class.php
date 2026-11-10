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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * backup/moodle2/restore_lumen10voiceaccess_activity_task.class.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/restore_lumen10voiceaccess_stepslib.php');
class restore_lumen10voiceaccess_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_lumen10voiceaccess_activity_structure_step('voiceaccess_structure', 'voiceaccess.xml'));
    }
    public static function define_decode_contents() {
        return [new restore_decode_content('lumen10voiceaccess', ['intro'], 'lumen10voiceaccess')];
    }
    public static function define_decode_rules() {
        return [new restore_decode_rule('LUMEN10VOICEACCESSVIEWBYID', '/mod/lumen10voiceaccess/view.php?id=$1', 'course_module')];
    }
    public static function define_restore_log_rules() { return []; }
}

