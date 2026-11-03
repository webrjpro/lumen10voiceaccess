<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');
$owner = (string)($_SERVER['HTTP_X_LUMEN10_OWNER'] ?? '');
$allowed = \local_lumen10_voiceaccess\access::session_allowed($owner);
\core\session\manager::write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['allowed' => $allowed]);
