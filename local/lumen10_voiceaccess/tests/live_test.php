<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;

defined('MOODLE_INTERNAL') || die();

use local_lumen10_voiceaccess\ai\gemini;
use local_lumen10_voiceaccess\ai\live;

/** Constrained sessions, shared quotas and untrusted client usage reports. */
final class live_test extends \advanced_testcase {
    private function candidate_user(): \stdClass {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/filelib.php');
        $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-server-only-key-ABCD';
        foreach (['enabled' => 1, 'allowai' => 1, 'dailyquota' => 40, 'gemini_enabled' => 1,
                'gemini_audience' => 'all', 'gemini_projectdaily' => 100, 'gemini_live_enabled' => 1,
                'gemini_live_audience' => 'all'] as $key => $value) {
            set_config($key, $value, preferences::COMPONENT);
        }
        $user = $this->getDataGenerator()->create_user();
        access::grant($user->id, 'blind', true);
        return $user;
    }

    public function test_live_pilot_is_independent_and_fail_closed_without_changing_flash(): void {
        $user = $this->candidate_user();
        set_config('gemini_live_audience', 'pilot', preferences::COMPONENT);
        $this->assertFalse(live::enabled($user->id));
        $this->assertTrue(gemini::eligible($user->id));
        set_config('gemini_live_pilotusers', 'otherperson; ' . strtoupper($user->username), preferences::COMPONENT);
        $this->assertTrue(live::enabled($user->id));
        set_config('gemini_live_enabled', 0, preferences::COMPONENT);
        $this->assertFalse(live::enabled($user->id));
        $this->assertTrue(gemini::eligible($user->id));
        set_config('gemini_live_enabled', 1, preferences::COMPONENT);
        access::revoke($user->id);
        $this->assertFalse(live::enabled($user->id));
    }

    public function test_setup_is_locked_to_audio_and_safe_actions(): void {
        $this->resetAfterTest();
        $setup = live::setup('untrusted-voice');
        $this->assertSame('models/gemini-3.8-live', $setup['model']);
        $this->assertSame('Sulafat', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertSame(['AUDIO'], $setup['generationConfig']['responseModalities']);
        $this->assertSame('START_OF_ACTIVITY_INTERRUPTS', $setup['realtimeInputConfig']['activityHandling']);
        $intents = $setup['tools'][0]['functionDeclarations'][0]['parameters']['properties']['intent']['enum'];
        foreach (['CONFIRM', 'SUBMIT', 'CLICK', 'OPEN_URL', 'DELETE', 'OPEN_ITEM_1'] as $unsafe) {
            $this->assertNotContains($unsafe, $intents);
        }
        $this->assertContains('OPEN_COURSES', $intents);
        $this->assertContains('STOP_MIC', $intents);
        $this->assertContains('OPEN_CHOICE', $intents);
        $this->assertSame('INTEGER', $setup['tools'][0]['functionDeclarations'][0]['parameters']['properties']['choice']['type']);
        $this->assertArrayNotHasKey('sessionResumption', $setup);
    }

    public function test_start_returns_only_ephemeral_secret_and_reserves_shared_quota(): void {
        global $DB;
        $user = $this->candidate_user();
        \curl::mock_response('{"name":"auth_tokens/synthetic-live-authorization"}');
        $result = live::start($user->id, 'Sulafat', true);
        $this->assertSame('auth_tokens/synthetic-live-authorization', $result['token']);
        $this->assertSame(live::SOCKET, $result['socket']);
        $this->assertSame(32, strlen($result['session']));
        $this->assertStringNotContainsString('synthetic-server-only-key', json_encode($result));
        $this->assertEquals(1, preferences::get($user->id)->quotaused);
        $this->assertEquals(1, gemini::project_used_today());
        $usage = $DB->get_record(gemini::TABLE, ['userid' => $user->id]);
        $this->assertSame(gemini::live_model(), $usage->model);
        $this->assertEquals(0, $usage->errors);
        try {
            live::start($user->id, 'Sulafat', true);
            $this->fail('Immediate duplicate token request was accepted');
        } catch (\moodle_exception $exception) {
            $this->assertSame('ratelimited', $exception->errorcode);
        }
        $this->assertEquals(1, preferences::get($user->id)->quotaused);
    }

    public function test_disabled_or_missing_consent_never_consumes_quota(): void {
        $user = $this->candidate_user();
        foreach ([false, true] as $consent) {
            if ($consent) {set_config('gemini_live_enabled', 0, preferences::COMPONENT);}
            try {
                live::start($user->id, 'Sulafat', $consent);
                $this->fail('Unauthorized Live session was accepted');
            } catch (\moodle_exception $exception) {
                $this->assertSame('notapproved', $exception->errorcode);
            }
        }
        $this->assertEquals(0, preferences::get($user->id)->quotaused);
        $this->assertEquals(0, gemini::project_used_today());
    }

    public function test_provider_failure_remains_a_charged_attempt_with_sanitized_error(): void {
        global $DB;
        $user = $this->candidate_user();
        \curl::mock_response('{"error":{"code":403,"message":"synthetic sensitive provider detail"}}');
        try {
            live::start($user->id, 'Sulafat', true);
            $this->fail('Failed provider response was accepted');
        } catch (\moodle_exception $exception) {
            $this->assertStringNotContainsString('sensitive provider', $exception->getMessage());
        }
        $usage = $DB->get_record(gemini::TABLE, ['userid' => $user->id]);
        $this->assertEquals(1, $usage->errors);
        $this->assertEquals(1, $usage->requests);
        $this->assertEquals(1, preferences::get($user->id)->quotaused);
    }

    public function test_usage_is_monotonic_owner_scoped_and_cannot_refund_or_reopen(): void {
        global $DB;
        $user = $this->candidate_user();
        \curl::mock_response('{"name":"auth_tokens/synthetic-live-authorization"}');
        $result = live::start($user->id, 'Sulafat', true);
        $id = $result['session'];
        live::report($user->id + 1000, $id, 99, 99, false);
        $this->assertEquals(0, $DB->get_field(gemini::TABLE, 'prompttokens', ['userid' => $user->id]));
        live::report($user->id, $id, 20, 10, false);
        live::report($user->id, $id, -5, -5, false);
        $usage = $DB->get_record(gemini::TABLE, ['userid' => $user->id]);
        $this->assertEquals(20, $usage->prompttokens);
        $this->assertEquals(10, $usage->outputtokens);
        live::report($user->id, $id, 25, 15, true);
        live::report($user->id, $id, 10000, 10000, false);
        $usage = $DB->get_record(gemini::TABLE, ['userid' => $user->id]);
        $this->assertEquals(25, $usage->prompttokens);
        $this->assertEquals(15, $usage->outputtokens);
        $this->assertEquals(1, preferences::get($user->id)->quotaused);
    }
}
