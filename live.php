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
 * live.php.
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
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$action = required_param('action', PARAM_ALPHA);
$userid = (int)$USER->id;
$introduced = (int)($SESSION->local_l10va_introduced_user ?? 0) === $userid;
if ($action === 'introduced') {
    // Session-local presentation state, never a transcript or permanent user preference.
    $SESSION->local_l10va_introduced_user = $userid;
    \core\session\manager::write_close();
    echo json_encode(['ok' => true]);
    exit;
}
\core\session\manager::write_close();
try {
    if ($action === 'start') {
        $result = \mod_lumen10voiceaccess\ai\live::start($userid,
            optional_param('voice', '', PARAM_ALPHA), (bool)optional_param('consent', 0, PARAM_BOOL));
        $result['introduced'] = $introduced;
    } else if ($action === 'usage' || $action === 'stop') {
        $id = required_param('session', PARAM_ALPHANUM);
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new moodle_exception('invalidrequest');
        }
        $metrics = ['reason' => optional_param('reason', 'stopped', PARAM_ALPHA)];
        foreach (['connectms', 'firstaudioms', 'actions', 'actionerrors', 'rejected'] as $field) {
            $metrics[$field] = optional_param($field, 0, PARAM_INT);
        }
        $owned = \mod_lumen10voiceaccess\ai\live::report($userid, $id,
            optional_param('input', 0, PARAM_INT), optional_param('output', 0, PARAM_INT), $action === 'stop', $metrics);
        $result = ['ok' => true, 'allowed' => $owned && \mod_lumen10voiceaccess\ai\live::enabled($userid)];
    } else {
        throw new moodle_exception('invalidrequest');
    }
} catch (Throwable $e) {
    // Do not serialize upstream bodies, the institutional key, credentials or exception details.
    $code = $e instanceof moodle_exception ? $e->errorcode : '';
    $result = ['error' => $code === 'livecapacity' ? 'busy' : ($code === 'ratelimited' ? 'limited' : 'unavailable')];
}
echo json_encode($result);
