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
 * classes/upgrade_migration.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Move the legacy local component into this activity without copying credentials or changing record IDs. */
final class upgrade_migration {
    public const TABLES = ['user', 'access', 'device', 'aiusage', 'live'];

    /** Check the complete plan before any DDL, then rename under Moodle's native upgrade lock. */
    public static function migrate(): void {
        global $CFG, $DB;
        $manager = $DB->get_manager();
        $plan = [];
        foreach (self::TABLES as $suffix) {
            $target = 'lumen10voiceaccess_' . $suffix;
            $sources = [];
            foreach (['local_lumen10_voiceaccess_', 'local_l10va_'] as $prefix) {
                $candidate = $prefix . $suffix;
                if ($manager->table_exists(new \xmldb_table($candidate))) {
                    $sources[] = $candidate;
                }
            }
            if (count($sources) > 1 || ($sources && $manager->table_exists(new \xmldb_table($target)) &&
                    $DB->count_records($target) > 0)) {
                throw new \upgrade_exception('mod_lumen10voiceaccess', 2026111001,
                    'Ambiguous VoiceAccess table migration; no tables have been changed.');
            }
            $plan[$target] = $sources[0] ?? null;
        }
        $schema = new \xmldb_file($CFG->dirroot . '/mod/lumen10voiceaccess/db/install.xml');
        if (!$schema->loadXMLStructure()) {
            throw new \upgrade_exception('mod_lumen10voiceaccess', 2026111001, 'Invalid unified VoiceAccess schema');
        }
        foreach ($plan as $target => $source) {
            if ($source) {
                // Fresh installation creates empty destination tables before the install callback.
                if ($manager->table_exists(new \xmldb_table($target))) {
                    $manager->drop_table(new \xmldb_table($target));
                }
                $manager->rename_table(new \xmldb_table($source), $target);
            } else if (!$manager->table_exists(new \xmldb_table($target))) {
                $manager->create_table($schema->getStructure()->getTable($target));
            }
        }
        $legacy = (array)get_config('local_lumen10_voiceaccess');
        foreach ($legacy as $name => $value) {
            if ($name !== 'version' && get_config('mod_lumen10voiceaccess', $name) === false) {
                set_config($name, $value, 'mod_lumen10voiceaccess');
            }
        }
        // Preserve explicit grants, prohibitions and role scopes before native legacy uninstallation.
        // Moodle normally registers new capabilities after the upgrade callback; the migration needs them now.
        update_capabilities('mod_lumen10voiceaccess');
        foreach (['use', 'manage'] as $permission) {
            $old = 'local/lumen10_voiceaccess:' . $permission;
            $new = 'mod/lumen10voiceaccess:' . $permission;
            foreach ($DB->get_records('role_capabilities', ['capability' => $old]) as $assignment) {
                assign_capability($new, $assignment->permission, $assignment->roleid, $assignment->contextid, true);
            }
        }
        // Quiesce legacy hooks until the administrator/deployment completes native uninstallation.
        if (!empty($legacy['version'])) {
            set_config('enabled', 0, 'local_lumen10_voiceaccess');
        }
        // Preserve legacy tables under new names, device cookies and server-secret locations unchanged.
        unset_config('allversionshash');
        unset_config('allcomponenthash');
    }
}
