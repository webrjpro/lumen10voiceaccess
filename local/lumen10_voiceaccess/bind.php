<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
$c = 'local_lumen10_voiceaccess';
$done = false;
$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $credential = \local_lumen10_voiceaccess\access::claim_pair(required_param('token', PARAM_ALPHANUM));
        setcookie(\local_lumen10_voiceaccess\access::cookie_name(), $credential, [
            'expires' => time() + \local_lumen10_voiceaccess\access::COOKIE_DAYS * DAYSECS,
            'path' => rtrim(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '', '/') . '/',
            'secure' => parse_url($CFG->wwwroot, PHP_URL_SCHEME) === 'https',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        $done = true;
    } catch (Throwable $e) {
        $error = true;
    }
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="' . s(str_replace('_', '-', current_language())) . '"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . s(get_string('pairdevice', $c)) . '</title>';
echo '<script src="' . s((new moodle_url('/local/lumen10_voiceaccess/bind.js'))->out(false)) . '"></script>';
echo '<link rel="stylesheet" href="' . s((new moodle_url('/local/lumen10_voiceaccess/styles.css'))->out(false)) . '"></head>';
echo '<body class="l10va-bind"><main><h1>' . s(get_string('pairdevice', $c)) . '</h1>';
echo '<p>' . s(get_string('pairdisclosure', $c)) . '</p>';
if ($done) {
    echo '<p role="status">' . s(get_string('pairdone', $c)) . '</p>';
    echo html_writer::link(new moodle_url('/login/index.php'), get_string('login'));
} else if ($error) {
    echo '<p role="alert">' . s(get_string('pairinvalid', $c)) . '</p>';
} else {
    echo '<form method="post"><input type="hidden" name="sesskey" value="' . s(sesskey()) . '">';
    echo '<input type="hidden" name="token" id="l10va-bind-token"><button type="submit" id="l10va-bind-submit" disabled>' .
        s(get_string('pairconfirm', $c)) . '</button></form>';
    echo '<noscript><p>' . s(get_string('pairjavascript', $c)) . '</p></noscript>';
}
echo '</main></body></html>';
