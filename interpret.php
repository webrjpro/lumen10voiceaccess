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
 * interpret.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();
\mod_lumen10voiceaccess\access::require_use();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !get_config('mod_lumen10voiceaccess', 'enabled')) {
    throw new moodle_exception('nopermissions', 'error', '', 'VoiceAccess');
}
$command = required_param('command', PARAM_RAW_TRIMMED);
$reason = optional_param('reason', 'unknown', PARAM_ALPHA);
\core\session\manager::write_close();
try {
    $result = \mod_lumen10voiceaccess\ai\router::interpret((int)$USER->id, $command, $reason);
} catch (Throwable $e) {
    // Never send upstream response bodies, request headers or credentials to the browser.
    $result = ['intent' => 'UNKNOWN', 'error' =>
        $e instanceof moodle_exception && $e->errorcode === 'ratelimited' ? 'limited' : 'unavailable'];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($result);

