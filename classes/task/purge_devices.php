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
 * classes/task/purge_devices.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess\task;
defined('MOODLE_INTERNAL') || die();
final class purge_devices extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('purgeexpireddevices', 'mod_lumen10voiceaccess');
    }
    public function execute(): void {
        global $DB;
        \mod_lumen10voiceaccess\operations::purge();
        $DB->delete_records_select('lumen10voiceaccess_device', 'expires <= :now OR (kind=:kind AND used=1)',
            ['now' => time(), 'kind' => 'pair']);
    }
}
