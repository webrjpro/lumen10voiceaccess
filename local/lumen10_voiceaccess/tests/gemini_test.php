<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

use local_lumen10_voiceaccess\ai\gemini;
use local_lumen10_voiceaccess\ai\router;

final class gemini_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
    }

    public function test_defaults_and_masked_key(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->assertSame(gemini::DEFAULT_TEXT_MODEL, gemini::text_model());
        $this->assertSame(gemini::DEFAULT_LIVE_MODEL, gemini::live_model());
        $this->assertSame('Sulafat', gemini::default_voice());
        $this->assertSame('female', gemini::VOICES[gemini::default_voice()]);

        $prev = $CFG->local_lumen10_voiceaccess_geminikey ?? null;
        try {
            $CFG->local_lumen10_voiceaccess_geminikey = 'AIzaSyFakeKey1234567890TestABCD';
            $this->assertTrue(gemini::configured());
            $this->assertSame('config', gemini::key_source());
            $this->assertSame('••••••••ABCD', gemini::masked_key());
        } finally {
            if ($prev !== null) {
                $CFG->local_lumen10_voiceaccess_geminikey = $prev;
            } else {
                unset($CFG->local_lumen10_voiceaccess_geminikey);
            }
        }
    }

    public function test_protected_secret_file_works_without_a_config_key(): void {
        global $CFG;
        $this->resetAfterTest();
        unset($CFG->local_lumen10_voiceaccess_geminikey);
        $path = make_request_directory() . '/gemini.key';
        file_put_contents($path, 'synthetic-file-secret-ABCD' . "\n");
        $previous = getenv(gemini::FILE_ENV);
        putenv(gemini::FILE_ENV . '=' . $path);
        try {
            $this->assertTrue(gemini::configured());
            $this->assertSame('file', gemini::key_source());
            $this->assertSame('••••••••ABCD', gemini::masked_key());
            $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-config-key-WXYZ';
            $this->assertSame('config', gemini::key_source());
            $this->assertSame('••••••••WXYZ', gemini::masked_key());
            unset($CFG->local_lumen10_voiceaccess_geminikey);
            file_put_contents($path, "invalid\nmultiline\nsecret");
            $this->assertFalse(gemini::configured());
        } finally {
            putenv($previous === false ? gemini::FILE_ENV : gemini::FILE_ENV . '=' . $previous);
        }
    }

    public function test_eligibility_and_audience(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-key-for-test-1234';
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('gemini_enabled', 1, preferences::COMPONENT);

        $g = $this->getDataGenerator();
        $u1 = $g->create_user(['username' => 'pilotstudent']);
        $u2 = $g->create_user(['username' => 'otherstudent']);

        // Default audience: pilot.
        set_config('gemini_audience', 'pilot', preferences::COMPONENT);
        set_config('gemini_pilotusers', 'pilotstudent, teacher1', preferences::COMPONENT);

        $this->assertTrue(gemini::eligible($u1->id));
        $this->assertFalse(gemini::eligible($u2->id));

        // Switch to all.
        set_config('gemini_audience', 'all', preferences::COMPONENT);
        $this->assertTrue(gemini::eligible($u2->id));

        // When gemini_enabled = 0, neither is eligible.
        set_config('gemini_enabled', 0, preferences::COMPONENT);
        $this->assertFalse(gemini::eligible($u1->id));
        $this->assertFalse(gemini::eligible($u2->id));
    }

    public function test_effective_ai_selects_gemini_without_exposing_secret(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_lumen10_voiceaccess_geminikey = 'secret-not-in-db-1234';
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('gemini_enabled', 1, preferences::COMPONENT);
        set_config('gemini_audience', 'all', preferences::COMPONENT);

        $u = $this->getDataGenerator()->create_user();
        $row = preferences::get($u->id);
        // Provider availability must not manufacture the participant's permission.
        $this->assertSame(0, $row->consent);
        $this->assertSame('none', preferences::effective_ai($row)->provider);
        $this->assertSame(0, preferences::effective_ai($row)->consent);
        $row->consent = 1;
        $eff = preferences::effective_ai($row);

        $this->assertSame(gemini::PROVIDER, $eff->provider);
        $this->assertSame(gemini::text_model(), $eff->model);
        $this->assertSame('server-env', $eff->apikey);
        $this->assertSame(1, $eff->consent);
        $this->assertSame('configured', readiness::ai($eff));
    }

    public function test_router_gemini_interpretation_and_aggregated_usage(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');

        $CFG->local_lumen10_voiceaccess_geminikey = 'secret-gemini-key-1234';
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('dailyquota', 50, preferences::COMPONENT);
        set_config('gemini_enabled', 1, preferences::COMPONENT);
        set_config('gemini_audience', 'all', preferences::COMPONENT);
        set_config('gemini_projectdaily', 100, preferences::COMPONENT);

        $u = $this->getDataGenerator()->create_user();
        access::grant($u->id, 'blind', true);

        // With no personal opt-in, even an approved participant must not consume AI quota.
        $this->setUser($u);
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'abrir meus cursos'));
        $this->assertSame(0, preferences::get($u->id)->quotaused);
        $this->assertFalse($DB->record_exists('local_lumen10_voiceaccess_aiusage', ['userid' => $u->id]));
        preferences::save($u->id, array_replace(preferences::defaults(), [
            'provider' => 'none', 'model' => gemini::DEFAULT_TEXT_MODEL, 'consent' => 1,
        ]));

        $mockPayload = json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"intent":"OPEN_COURSES","confidence":0.98}']
                    ],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 42,
                'candidatesTokenCount' => 18,
            ],
        ]);
        \curl::mock_response($mockPayload);

        $result = router::interpret($u->id, 'abrir meus cursos');
        $this->assertSame(['intent' => 'OPEN_COURSES'], $result);

        // Verify aggregated usage table.
        $usage = $DB->get_record('local_lumen10_voiceaccess_aiusage', ['userid' => $u->id, 'provider' => gemini::PROVIDER]);
        $this->assertNotEmpty($usage);
        $this->assertEquals(1, $usage->requests);
        $this->assertEquals(0, $usage->errors);
        $this->assertEquals(42, $usage->prompttokens);
        $this->assertEquals(18, $usage->outputtokens);

        // Verify personal quota was also incremented.
        $this->assertEquals(1, preferences::get($u->id)->quotaused);

        // Test project-wide daily cap.
        set_config('gemini_projectdaily', 1, preferences::COMPONENT);
        $DB->set_field('local_lumen10_voiceaccess_user', 'lastrequest', time() - 4, ['userid' => $u->id]);
        $this->expectException(\moodle_exception::class);
        router::interpret($u->id, 'ver notas');
    }

    public function test_test_connection_synthetic_phrase(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');

        $CFG->local_lumen10_voiceaccess_geminikey = 'secret-test-key-1234';
        set_config('enabled', 1, preferences::COMPONENT);

        $mockPayload = json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"intent":"OPEN_COURSES","confidence":0.99}']
                    ],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 20,
                'candidatesTokenCount' => 10,
            ],
        ]);
        \curl::mock_response($mockPayload);

        $test = gemini::test_connection();
        $this->assertTrue($test['ok']);
        $this->assertSame('OPEN_COURSES', $test['detail']);
        $this->assertGreaterThanOrEqual(0, $test['ms']);
    }

    public function test_privacy_erasure_removes_aiusage(): void {
        global $DB;
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        access::grant($u->id, 'blind', true);

        gemini::record_usage($u->id, gemini::DEFAULT_TEXT_MODEL, true, 50, 20);
        $this->assertTrue($DB->record_exists('local_lumen10_voiceaccess_aiusage', ['userid' => $u->id]));

        access::erase_user_data($u->id);
        $this->assertFalse($DB->record_exists('local_lumen10_voiceaccess_aiusage', ['userid' => $u->id]));
    }

    public function test_connection_test_respects_project_limit_without_counting_a_rejected_attempt(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-key-for-test';
        set_config('gemini_projectdaily', 0, preferences::COMPONENT);
        $result = gemini::test_connection();
        $this->assertFalse($result['ok']);
        $this->assertSame('ratelimited', $result['detail']);
        $this->assertSame(0, gemini::project_used_today());
    }

    public function test_failed_upstream_attempt_still_uses_project_quota(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-key-for-test';
        set_config('gemini_projectdaily', 1, preferences::COMPONENT);
        \curl::mock_response('invalid upstream JSON');
        $result = gemini::test_connection();
        $this->assertFalse($result['ok']);
        $this->assertSame(1, gemini::project_used_today());
        $row = $DB->get_record(gemini::TABLE, ['userid' => 0], '*', MUST_EXIST);
        $this->assertEquals(1, $row->errors);
        $this->assertSame('ratelimited', gemini::test_connection()['detail']);
        $this->assertSame(1, gemini::project_used_today());
    }

    public function test_analyst_cannot_replace_institutional_credentials_or_ai_limits(): void {
        $this->resetAfterTest();
        $c = preferences::COMPONENT;
        set_config('allowai', 1, $c);
        set_config('dailyquota', 40, $c);
        set_config('inst_provider', 'openai', $c);
        set_config('inst_model', 'synthetic-model', $c);
        set_config('inst_apikey', 'opaque-existing-ciphertext', $c);
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/lumen10_voiceaccess:manage', CAP_ALLOW, $role, \context_system::instance());
        role_assign($role, $user->id, \context_system::instance());
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/site:config', \context_system::instance()));
        preferences::save_institutional(['enabled' => 1, 'spoken' => 1, 'allowai' => 0, 'dailyquota' => 1000,
            'provider' => 'anthropic', 'model' => 'replacement-model', 'apikey' => 'replacement-key', 'removekey' => 1]);
        $this->assertEquals(1, get_config($c, 'allowai'));
        $this->assertEquals(40, get_config($c, 'dailyquota'));
        $this->assertSame('openai', get_config($c, 'inst_provider'));
        $this->assertSame('synthetic-model', get_config($c, 'inst_model'));
        $this->assertSame('opaque-existing-ciphertext', get_config($c, 'inst_apikey'));
        $this->assertTrue(preferences::defaults()['spoken']);
    }

    public function test_saving_voice_settings_preserves_admin_ai_configuration(): void {
        $this->resetAfterTest();
        $c = preferences::COMPONENT;
        set_config('allowai', 1, $c);
        set_config('dailyquota', 40, $c);
        set_config('inst_provider', 'openai', $c);
        set_config('inst_apikey', 'opaque-existing-ciphertext', $c);
        preferences::save_institutional(['enabled' => 1, 'spoken' => 1]);
        $this->assertEquals(1, get_config($c, 'allowai'));
        $this->assertEquals(40, get_config($c, 'dailyquota'));
        $this->assertSame('openai', get_config($c, 'inst_provider'));
        $this->assertSame('opaque-existing-ciphertext', get_config($c, 'inst_apikey'));
    }
}
