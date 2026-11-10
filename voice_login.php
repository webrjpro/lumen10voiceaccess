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
 * voice_login.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', false);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$logingate = get_config('mod_lumen10voiceaccess', 'loginvoicegate') === false ||
    (bool)get_config('mod_lumen10voiceaccess', 'loginvoicegate');
$sessionkey = optional_param('sesskey', '', PARAM_ALPHANUM);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' ||
        $sessionkey === '' || !confirm_sesskey($sessionkey) || (isloggedin() && !isguestuser()) ||
        !get_config('mod_lumen10voiceaccess', 'enabled') ||
        !$logingate ||
        !get_config('mod_lumen10voiceaccess', 'version')) {
    echo json_encode(['ok' => false]);
    exit;
}

$phrase = optional_param('phrase', '', PARAM_RAW_TRIMMED);
if (\core_text::strlen($phrase) < 4 || \core_text::strlen($phrase) > 200) {
    echo json_encode(['ok' => false]);
    exit;
}

if (!\mod_lumen10voiceaccess\login_guard::consume_attempt(getremoteaddr('0.0.0.0'))) {
    echo json_encode(['ok' => false]);
    exit;
}

$ipkey = 'vlogin-' . substr(hash('sha256', getremoteaddr('0.0.0.0')), 0, 16);
$lock = \core\lock\lock_config::get_lock_factory('mod_lumen10voiceaccess')->get_lock($ipkey, 3);
if (!$lock) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $userid = \mod_lumen10voiceaccess\access::verify_voice_phrase($phrase);
    if ($userid <= 0) {
        usleep(350000);
        echo json_encode(['ok' => false]);
        exit;
    }
    $user = get_complete_user_data('id', $userid);
    if (!$user || !\mod_lumen10voiceaccess\login_guard::eligible($user)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    complete_user_login($user);
    $device = \mod_lumen10voiceaccess\access::issue_device_cookie($userid);
    $path = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '/';
    setcookie(\mod_lumen10voiceaccess\access::cookie_name(), $device, [
        'expires' => time() + \mod_lumen10voiceaccess\access::COOKIE_DAYS * DAYSECS,
        'path' => rtrim($path, '/') . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    echo json_encode([
        'ok' => true,
        'userid' => (int)$user->id,
        'firstname' => (string)$user->firstname,
        'redirect' => (new moodle_url('/my/'))->out(false),
    ]);
} finally {
    $lock->release();
}
