<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();
\local_lumen10_voiceaccess\access::require_use();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !get_config('local_lumen10_voiceaccess', 'enabled')) {
    throw new moodle_exception('nopermissions', 'error', '', 'VoiceAccess');
}
$command = required_param('command', PARAM_RAW_TRIMMED);
$reason = optional_param('reason', 'unknown', PARAM_ALPHA);
\core\session\manager::write_close();
try {
    $result = \local_lumen10_voiceaccess\ai\router::interpret((int)$USER->id, $command, $reason);
} catch (Throwable $e) {
    // Never send upstream response bodies, request headers or credentials to the browser.
    $result = ['intent' => 'UNKNOWN', 'error' =>
        $e instanceof moodle_exception && $e->errorcode === 'ratelimited' ? 'limited' : 'unavailable'];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($result);

