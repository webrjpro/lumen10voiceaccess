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
 * classes/event/access_updated.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess\event;
defined('MOODLE_INTERNAL') || die();
final class access_updated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    public static function get_name(): string {
        return get_string('accessupdated', 'mod_lumen10voiceaccess');
    }
    public function get_description(): string {
        return 'User ' . $this->userid . ' updated VoiceAccess approval for user ' . $this->relateduserid . '.';
    }
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/lumen10voiceaccess/manage.php');
    }
}
