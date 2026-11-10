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
 * status.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');
$owner = (string)($_SERVER['HTTP_X_LUMEN10_OWNER'] ?? '');
$allowed = \mod_lumen10voiceaccess\access::session_allowed($owner);
\core\session\manager::write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['allowed' => $allowed]);
