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
 * bind.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
$c = 'mod_lumen10voiceaccess';
$done = false;
$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $credential = \mod_lumen10voiceaccess\access::claim_pair(required_param('token', PARAM_ALPHANUM));
        setcookie(\mod_lumen10voiceaccess\access::cookie_name(), $credential, [
            'expires' => time() + \mod_lumen10voiceaccess\access::COOKIE_DAYS * DAYSECS,
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
echo '<script src="' . s((new moodle_url('/mod/lumen10voiceaccess/bind.js'))->out(false)) . '"></script>';
echo '<link rel="stylesheet" href="' . s((new moodle_url('/mod/lumen10voiceaccess/styles.css'))->out(false)) . '"></head>';
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
