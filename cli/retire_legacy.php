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
 * cli/retire_legacy.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/upgradelib.php');

[$options, $unknown] = cli_get_params(['execute' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || $unknown) {
    echo "Retire the migrated local_lumen10_voiceaccess registration.\n" .
        "Run after backup, native unified-plugin upgrade and record verification.\n" .
        "Default: read-only preflight. --execute: native legacy uninstallation.\n" .
        "Preserve the legacy source outside Moodle, then remove it from local/ before reopening the site.\n";
    exit($unknown ? 1 : 0);
}
if ((int)get_config('mod_lumen10voiceaccess', 'version') < 2026111001) {
    cli_error('Upgrade the unified activity before retiring the legacy local plugin.');
}
$manager = $DB->get_manager();
foreach (\mod_lumen10voiceaccess\upgrade_migration::TABLES as $suffix) {
    if (!$manager->table_exists(new xmldb_table('lumen10voiceaccess_' . $suffix))) {
        cli_error('Unified table missing; no legacy registration was removed.');
    }
    foreach (['local_l10va_', 'local_lumen10_voiceaccess_'] as $prefix) {
        if ($manager->table_exists(new xmldb_table($prefix . $suffix))) {
            cli_error('Unmigrated legacy table remains; retirement refused.');
        }
    }
}
if ($DB->record_exists('files', ['component' => 'local_lumen10_voiceaccess'])) {
    cli_error('Legacy File API records require a separate reviewed migration; retirement refused.');
}
$legacy = get_config('local_lumen10_voiceaccess', 'version');
if ($options['execute'] && $legacy) {
    if (empty($CFG->maintenance_enabled)) {
        cli_error('Enable maintenance mode before retiring the legacy registration.');
    }
    \core\session\manager::set_user(get_admin());
    if (!core_plugin_manager::instance()->uninstall_plugin('local_lumen10_voiceaccess', new null_progress_trace())) {
        cli_error('Native legacy uninstallation was not completed.');
    }
}
echo json_encode(['passed' => true, 'executed' => (bool)$options['execute'],
    'legacy_registered' => (bool)get_config('local_lumen10_voiceaccess', 'version')]) . PHP_EOL;
