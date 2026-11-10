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
 * tests/preferences_test.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
defined('MOODLE_INTERNAL') || die();

use mod_lumen10voiceaccess\ai\router;

final class preferences_test extends \advanced_testcase {
    private function save(int $userid, array $extra = []): void {
        $this->setAdminUser();
        set_config('enabled', 1, preferences::COMPONENT);
        access::grant($userid, 'blind', true);
        preferences::save($userid, $extra + preferences::defaults() + [
            'provider' => 'openai', 'model' => 'gpt-6-luna', 'consent' => 1,
            'apikey' => 'synthetic-test-key-never-real',
        ]);
    }

    public function test_luna_is_default_and_credentials_are_encrypted_and_isolated(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->assertSame('gpt-6-luna', preferences::get($a->id)->model);
        $this->save($a->id, ['voiceuri' => 'device-voice', 'volume' => 0.5]);
        $row = preferences::get($a->id);
        $this->assertNotSame('synthetic-test-key-never-real', $row->apikey);
        $this->assertSame('synthetic-test-key-never-real', \core\encryption::decrypt($row->apikey));
        $this->assertEmpty(preferences::get($b->id)->apikey);
        $public = preferences::public_settings($row);
        $this->assertArrayNotHasKey('apikey', $public);
        $this->assertSame('device-voice', $public['voiceuri']);
        $this->assertSame(0.5, $public['volume']);
    }

    public function test_blank_key_preserves_ciphertext_and_switching_provider_erases_it(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id);
        $cipher = preferences::get($u->id)->apikey;
        $this->save($u->id, ['apikey' => '']);
        $this->assertSame($cipher, preferences::get($u->id)->apikey);
        $this->save($u->id, ['provider' => 'anthropic', 'model' => 'test-claude', 'apikey' => '']);
        $this->assertEmpty(preferences::get($u->id)->apikey);
    }

    public function test_preference_updates_cannot_reset_quota_and_can_revoke_key(): void {
        global $DB;
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id);
        $DB->set_field('lumen10voiceaccess_user', 'quotaused', 12, ['userid' => $u->id]);
        $this->save($u->id, ['removekey' => 1, 'apikey' => '']);
        $this->assertEmpty(preferences::get($u->id)->apikey);
        $this->assertEquals(12, preferences::get($u->id)->quotaused);
    }

    public function test_ai_rejects_writes_urls_confirmation_low_confidence_and_malformed_output(): void {
        foreach ([
            '{}', '[]', 'false', 'not json',
            '{"intent":"CONFIRM","confidence":1}', '{"intent":"DELETE","confidence":1}',
            '{"intent":"OPEN_URL","confidence":1,"url":"https://evil.invalid"}',
            '{"intent":"OPEN_GRADES","confidence":0.84}', '{"intent":"OPEN_GRADES","confidence":4}',
        ] as $output) {
            $this->assertSame(['intent' => 'UNKNOWN'], router::validate($output));
        }
        $this->assertSame(['intent' => 'OPEN_GRADES'],
            router::validate('{"intent":"OPEN_GRADES","confidence":0.96,"url":"discarded","selector":"discarded"}'));
    }

    public function test_disabled_ai_or_missing_consent_never_calls_network(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id, ['consent' => 0]);
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'minhas notas por favor'));
        set_config('allowai', 1, preferences::COMPONENT);
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'minhas notas por favor'));
    }

    public function test_openai_response_is_validated_and_attempts_are_limited(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('dailyquota', 1, preferences::COMPONENT);
        \curl::mock_response(json_encode(['output' => [['type' => 'message', 'content' => [
            ['type' => 'output_text', 'text' => '{"intent":"OPEN_GRADES","confidence":0.99}'],
        ]]]]));
        $this->assertSame(['intent' => 'OPEN_GRADES'], router::interpret($u->id, 'mostra quanto tirei'));
        $this->assertEquals(1, preferences::get($u->id)->quotaused);
        $DB->set_field('lumen10voiceaccess_user', 'lastrequest', time() - 4, ['userid' => $u->id]);
        $this->expectException(\moodle_exception::class);
        router::interpret($u->id, 'e agora?');
    }

    public function test_anthropic_response_has_the_same_safety_rules(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id, ['provider' => 'anthropic', 'model' => 'test-claude']);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('dailyquota', 10, preferences::COMPONENT);
        \curl::mock_response(json_encode(['content' => [['type' => 'text', 'text' => '{"intent":"CONFIRM","confidence":1}']]]));
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'enviar tudo'));
    }
    public function test_malformed_provider_envelopes_do_not_emit_php_warnings(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $user = $this->getDataGenerator()->create_user();
        $this->save($user->id);
        set_config('allowai', 1, preferences::COMPONENT);
        set_config('dailyquota', 40, preferences::COMPONENT);
        foreach (['false', '{"output":"invalid"}', '{"output":[false,{"type":"message","content":false}]}'] as $response) {
            $DB->set_field('lumen10voiceaccess_user', 'lastrequest', time() - 4, ['userid' => $user->id]);
            \curl::mock_response($response);
            try {
                $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($user->id, 'show grades'));
            } catch (\moodle_exception $e) {
                $this->assertSame('providererror', $e->errorcode);
            }
        }
        $this->assertEquals(3, preferences::get($user->id)->quotaused);
        $this->assertDebuggingNotCalled();
    }

    public function test_privacy_removal_erases_only_the_approved_user(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->save($a->id); $this->save($b->id);
        privacy\provider::delete_data_for_all_users_in_context(\context_user::instance($a->id));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_user', ['userid' => $a->id]));
        $this->assertTrue($DB->record_exists('lumen10voiceaccess_user', ['userid' => $b->id]));
    }
    public function test_login_captcha_stays_enabled_without_a_private_device_and_preserves_signup(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('version', 2026101601, preferences::COMPONENT);
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('loginwithoutcaptcha', 1, preferences::COMPONENT);
        $CFG->enableloginrecaptcha = 1;
        set_config('recaptcha', 1, 'auth_email');
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
        $this->assertEquals(1, get_config('auth_email', 'recaptcha'));
        set_config('loginwithoutcaptcha', 0, preferences::COMPONENT);
        $CFG->enableloginrecaptcha = 1; // Fresh request reloads the unchanged core setting.
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
    }
    public function test_secret_phrases_are_not_forwarded_to_ai(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->save($u->id);
        set_config('allowai', 1, preferences::COMPONENT);
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'minha senha secreta'));
        $this->assertSame(['intent' => 'UNKNOWN'], router::interpret($u->id, 'sk-proj-' . str_repeat('X', 40)));
        $this->assertEquals(0, preferences::get($u->id)->quotaused);
    }
    public function test_semantic_navigation_exposes_only_the_current_users_visible_activities(): void {
        $this->resetAfterTest();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $other = $g->create_course();
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        $visible = $g->create_module('page', ['course' => $course->id, 'name' => 'Aula visível']);
        $hidden = $g->create_module('page', ['course' => $course->id, 'name' => 'Aula oculta', 'visible' => 0]);
        $this->setUser($user);
        $snapshot = navigation::snapshot($user->id, $course, $visible->cmid);
        $this->assertCount(1, $snapshot['courses']);
        $this->assertCount(1, $snapshot['activities']);
        $this->assertEquals($visible->cmid, $snapshot['activities'][0]['id']);
        $this->assertStringNotContainsString('Aula oculta', json_encode($snapshot));
        $this->assertStringNotContainsString('id=' . $other->id . '"', json_encode($snapshot));
        $this->assertArrayNotHasKey('apikey', $snapshot);
    }
    public function test_recovery_intent_is_read_only_and_cannot_confirm_a_submission(): void {
        $this->assertSame(['intent' => 'LIST_CONTROLS'], router::validate('{"intent":"LIST_CONTROLS","confidence":0.95}'));
        $this->assertSame(['intent' => 'UNKNOWN'], router::validate('{"intent":"SELECT_ANSWER","confidence":1}'));
    }
}

