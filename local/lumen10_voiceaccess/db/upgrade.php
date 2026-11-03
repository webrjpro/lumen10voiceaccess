<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
function xmldb_local_lumen10_voiceaccess_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026101601) {
        $manager = $DB->get_manager();
        $file = new xmldb_file(__DIR__ . '/install.xml');
        if (!$file->loadXMLStructure()) {
            throw new upgrade_exception('local_lumen10_voiceaccess', 2026101601, 'Invalid access schema');
        }
        foreach (['local_l10va_access', 'local_l10va_device'] as $name) {
            $table = $file->getStructure()->getTable($name);
            if (!$manager->table_exists($table)) {
                $manager->create_table($table);
            }
        }
        // Existing preferences and keys do not imply a disability declaration or approval.
        upgrade_plugin_savepoint(true, 2026101601, 'local', 'lumen10_voiceaccess');
    }
    if ($oldversion < 2026101700) {
        $manager = $DB->get_manager();
        $file = new xmldb_file(__DIR__ . '/install.xml');
        if (!$file->loadXMLStructure()) {
            throw new upgrade_exception('local_lumen10_voiceaccess', 2026101700, 'Invalid AI usage schema');
        }
        $table = $file->getStructure()->getTable('local_l10va_aiusage');
        if (!$manager->table_exists($table)) {
            $manager->create_table($table);
        }
        // Gemini starts disabled; administrators opt in from the plugin settings.
        $defaults = ['gemini_enabled' => 0, 'gemini_textmodel' => 'gemini-3.5-flash-lite',
            'gemini_livemodel' => 'gemini-3.8-live', 'gemini_tier' => 'free', 'gemini_audience' => 'pilot',
            'gemini_pilotusers' => '', 'gemini_projectdaily' => 450, 'gemini_voice' => 'Sulafat'];
        foreach ($defaults as $name => $value) {
            if (get_config('local_lumen10_voiceaccess', $name) === false) {
                set_config($name, $value, 'local_lumen10_voiceaccess');
            }
        }
        upgrade_plugin_savepoint(true, 2026101700, 'local', 'lumen10_voiceaccess');
    }
    if ($oldversion < 2026101800) {
        // Server secret files, atomic AI quotas and management permission checks are code-only changes.
        upgrade_plugin_savepoint(true, 2026101800, 'local', 'lumen10_voiceaccess');
    }
    if ($oldversion < 2026101801) {
        // Live is offered only after administrators explicitly enable it.
        foreach (['gemini_live_enabled' => 0, 'gemini_live_seconds' => 180,
                'gemini_live_audience' => 'pilot', 'gemini_live_pilotusers' => ''] as $key => $value) {
            if (get_config('local_lumen10_voiceaccess', $key) === false) {
                set_config($key, $value, 'local_lumen10_voiceaccess');
            }
        }
        upgrade_plugin_savepoint(true, 2026101801, 'local', 'lumen10_voiceaccess');
    }
    if ($oldversion < 2026101802) {
        $manager = $DB->get_manager();
        $file = new xmldb_file(__DIR__ . '/install.xml');
        if (!$file->loadXMLStructure()) {
            throw new upgrade_exception('local_lumen10_voiceaccess', 2026101802, 'Invalid operational schema');
        }
        $table = $file->getStructure()->getTable('local_l10va_live');
        if (!$manager->table_exists($table)) {
            $manager->create_table($table);
        }
        // Additive migration only. Existing pilot, billing and quota settings are preserved.
        upgrade_plugin_savepoint(true, 2026101802, 'local', 'lumen10_voiceaccess');
    }
    return true;
}
