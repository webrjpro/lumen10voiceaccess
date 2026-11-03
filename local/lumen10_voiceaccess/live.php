<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();
\local_lumen10_voiceaccess\access::require_use();
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
        $result = \local_lumen10_voiceaccess\ai\live::start($userid,
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
        $owned = \local_lumen10_voiceaccess\ai\live::report($userid, $id,
            optional_param('input', 0, PARAM_INT), optional_param('output', 0, PARAM_INT), $action === 'stop', $metrics);
        $result = ['ok' => true, 'allowed' => $owned && \local_lumen10_voiceaccess\ai\live::enabled($userid)];
    } else {
        throw new moodle_exception('invalidrequest');
    }
} catch (Throwable $e) {
    // Do not serialize upstream bodies, the institutional key, credentials or exception details.
    $code = $e instanceof moodle_exception ? $e->errorcode : '';
    $result = ['error' => $code === 'livecapacity' ? 'busy' : ($code === 'ratelimited' ? 'limited' : 'unavailable')];
}
echo json_encode($result);
