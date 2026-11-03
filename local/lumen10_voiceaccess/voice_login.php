<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', false);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$logingate = get_config('local_lumen10_voiceaccess', 'loginvoicegate') === false ||
    (bool)get_config('local_lumen10_voiceaccess', 'loginvoicegate');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' ||
        !get_config('local_lumen10_voiceaccess', 'enabled') ||
        !$logingate ||
        !get_config('mod_lumen10voiceaccess', 'version')) {
    echo json_encode(['ok' => false]);
    exit;
}

$phrase = optional_param('phrase', '', PARAM_RAW_TRIMMED);
if ($phrase === '') {
    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($payload) && isset($payload['phrase']) && is_string($payload['phrase'])) {
        $phrase = trim($payload['phrase']);
    }
}
if (\core_text::strlen($phrase) < 4 || \core_text::strlen($phrase) > 200) {
    echo json_encode(['ok' => false]);
    exit;
}

$ipkey = 'vlogin-' . substr(hash('sha256', getremoteaddr('0.0.0.0')), 0, 16);
$lock = \core\lock\lock_config::get_lock_factory('local_lumen10_voiceaccess')->get_lock($ipkey, 3);
if (!$lock) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $userid = \local_lumen10_voiceaccess\access::verify_voice_phrase($phrase);
    if ($userid <= 0) {
        usleep(350000);
        echo json_encode(['ok' => false]);
        exit;
    }
    $user = get_complete_user_data('id', $userid);
    if (!$user || !empty($user->deleted) || !empty($user->suspended) || isguestuser($user)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $CFG->enableloginrecaptcha = 0;
    complete_user_login($user);
    $device = \local_lumen10_voiceaccess\access::issue_device_cookie($userid);
    $path = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '/';
    setcookie(\local_lumen10_voiceaccess\access::cookie_name(), $device, [
        'expires' => time() + \local_lumen10_voiceaccess\access::COOKIE_DAYS * DAYSECS,
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
