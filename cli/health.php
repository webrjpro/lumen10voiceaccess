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
 * cli/health.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unrecognized] = cli_get_params(['hours' => 24, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "VoiceAccess operational health, JSON only. --hours=1..720\nExit 0: no alert; 2: operational alert. No provider calls.\n";
    exit(0);
}
if ($unrecognized || !ctype_digit((string)$options['hours']) || (int)$options['hours'] < 1 || (int)$options['hours'] > 720) {
    cli_error('Use --hours=1..720');
}
$snapshot = \mod_lumen10voiceaccess\operations::snapshot((int)$options['hours']);
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($snapshot['alerts'] ? 2 : 0);
