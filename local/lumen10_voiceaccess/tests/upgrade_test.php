<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Exercise real XMLDB table renames with synthetic records in the PHPUnit database. */
final class upgrade_test extends \advanced_testcase {
    private const TABLES = [
        'local_l10va_user' => 'local_lumen10_voiceaccess_user',
        'local_l10va_access' => 'local_lumen10_voiceaccess_access',
        'local_l10va_device' => 'local_lumen10_voiceaccess_device',
        'local_l10va_aiusage' => 'local_lumen10_voiceaccess_aiusage',
        'local_l10va_live' => 'local_lumen10_voiceaccess_live',
    ];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/lumen10_voiceaccess/db/upgrade.php');
    }

    private function restore_tables(): void {
        global $DB;
        $manager = $DB->get_manager();
        foreach (self::TABLES as $legacy => $canonical) {
            if ($manager->table_exists(new \xmldb_table($legacy))) {
                if ($manager->table_exists(new \xmldb_table($canonical))) {
                    $manager->drop_table(new \xmldb_table($canonical));
                }
                $manager->rename_table(new \xmldb_table($legacy), $canonical);
            }
        }
    }

    private function upgrade_from(int $version): void {
        set_config('version', $version, preferences::COMPONENT);
        $this->assertTrue(\xmldb_local_lumen10_voiceaccess_upgrade($version));
    }

    public function test_legacy_upgrade_preserves_every_record_id_and_encrypted_key(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        set_config('enabled', 1, preferences::COMPONENT);
        access::grant($user->id, 'blind', true);
        preferences::save($user->id, preferences::defaults() + [
            'provider' => 'openai', 'model' => 'gpt-6-luna', 'consent' => 1,
            'apikey' => 'synthetic-migration-key-never-real',
        ]);
        access::issue_pair($user->id);
        $usageid = $DB->insert_record('local_lumen10_voiceaccess_aiusage', (object)[
            'day' => '2026-11-03', 'userid' => $user->id, 'provider' => 'gemini',
            'model' => 'synthetic-model', 'requests' => 3, 'errors' => 1,
            'prompttokens' => 10, 'outputtokens' => 5, 'timemodified' => time(),
        ]);
        $DB->insert_record('local_lumen10_voiceaccess_live', (object)[
            'sessionhash' => hash('sha256', 'synthetic-migration-session'),
            'userid' => $user->id, 'usageid' => $usageid, 'timecreated' => time(),
            'expires' => time() + 180, 'lastseen' => time(),
        ]);
        set_config('gemini_enabled', 1, preferences::COMPONENT);
        set_config('gemini_pilotusers', 'synthetic-pilot', preferences::COMPONENT);
        $before = [];
        $manager = $DB->get_manager();
        foreach (self::TABLES as $legacy => $canonical) {
            $before[$canonical] = $DB->get_records($canonical, [], 'id');
            $this->assertNotEmpty($before[$canonical]);
            $manager->rename_table(new \xmldb_table($canonical), $legacy);
        }
        try {
            $this->upgrade_from(2026110300);
            foreach (self::TABLES as $legacy => $canonical) {
                $this->assertFalse($manager->table_exists(new \xmldb_table($legacy)));
                $this->assertEquals($before[$canonical], $DB->get_records($canonical, [], 'id'));
            }
            $this->assertSame('synthetic-migration-key-never-real',
                \core\encryption::decrypt(preferences::get($user->id)->apikey));
            $this->assertTrue(access::allowed($user->id));
            $this->assertEquals(1, get_config(preferences::COMPONENT, 'gemini_enabled'));
            $this->assertSame('synthetic-pilot', get_config(preferences::COMPONENT, 'gemini_pilotusers'));
        } finally {
            $this->restore_tables();
        }
    }

    public function test_interrupted_rename_can_resume_without_duplicate_tables(): void {
        global $DB;
        $manager = $DB->get_manager();
        $manager->rename_table(new \xmldb_table('local_lumen10_voiceaccess_device'), 'local_l10va_device');
        try {
            $this->upgrade_from(2026101802);
            foreach (self::TABLES as $legacy => $canonical) {
                $this->assertFalse($manager->table_exists(new \xmldb_table($legacy)));
                $this->assertTrue($manager->table_exists(new \xmldb_table($canonical)));
            }
            $this->upgrade_from(2026110301);
        } finally {
            $this->restore_tables();
        }
    }

    public function test_conflicting_tables_abort_before_any_rename(): void {
        global $CFG, $DB;
        $manager = $DB->get_manager();
        $manager->rename_table(new \xmldb_table('local_lumen10_voiceaccess_user'), 'local_l10va_user');
        $file = new \xmldb_file($CFG->dirroot . '/local/lumen10_voiceaccess/db/install.xml');
        $this->assertTrue($file->loadXMLStructure());
        $table = $file->getStructure()->getTable('local_lumen10_voiceaccess_live');
        $table->setName('local_l10va_live');
        $manager->create_table($table);
        try {
            set_config('version', 2026110300, preferences::COMPONENT);
            try {
                \xmldb_local_lumen10_voiceaccess_upgrade(2026110300);
                $this->fail('Conflicting table pair must stop migration.');
            } catch (\upgrade_exception $exception) {
                $this->assertTrue($manager->table_exists(new \xmldb_table('local_l10va_user')));
                $this->assertFalse($manager->table_exists(new \xmldb_table('local_lumen10_voiceaccess_user')));
                $this->assertTrue($manager->table_exists(new \xmldb_table('local_l10va_live')));
                $this->assertTrue($manager->table_exists(new \xmldb_table('local_lumen10_voiceaccess_live')));
            }
        } finally {
            if ($manager->table_exists(new \xmldb_table('local_l10va_live'))) {
                $manager->drop_table(new \xmldb_table('local_l10va_live'));
            }
            $this->restore_tables();
        }
    }
}
