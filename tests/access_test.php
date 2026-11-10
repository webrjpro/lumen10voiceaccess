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
 * tests/access_test.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
defined('MOODLE_INTERNAL') || die();

final class access_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('version', 2026101601, preferences::COMPONENT);
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('publiclogin', 1, preferences::COMPONENT);
        set_config('loginwithoutcaptcha', 1, preferences::COMPONENT);
        $this->setAdminUser();
    }
    protected function tearDown(): void {
        global $SESSION;
        unset($_COOKIE[access::cookie_name()], $_COOKIE[login_policy::CONVENTIONAL_COOKIE], $_POST['username'], $SESSION->loginerrormsg);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }
    private function user(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        access::grant($user->id, 'blind', true);
        return $user;
    }
    private function pair(int $userid): string {
        $pair = access::issue_pair($userid);
        $device = access::claim_pair($pair);
        $_COOKIE[access::cookie_name()] = $device;
        return $pair;
    }
    public function test_admin_can_diagnose_but_unlisted_users_and_managers_cannot_use_access(): void {
        global $USER;
        $this->assertTrue(has_capability('mod/lumen10voiceaccess:use', \context_system::instance()));
        $this->assertTrue(access::allowed((int)$USER->id));
        access::require_use();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(access::allowed($user->id));
        $manager = $this->getDataGenerator()->create_user();
        $role = $GLOBALS['DB']->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($role->id, $manager->id, \context_system::instance());
        $this->assertFalse(access::allowed($manager->id));
        $this->assertFalse(access::public_allowed());
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        access::require_use();
    }
    public function test_declaration_and_confirmation_are_both_required(): void {
        $user = $this->getDataGenerator()->create_user();
        try { access::grant($user->id, 'blind', false); $this->fail('Missing confirmation accepted'); }
        catch (\moodle_exception $e) { $this->assertSame('invaliddeclaration', $e->errorcode); }
        try { access::grant($user->id, 'other', true); $this->fail('Unsupported declaration accepted'); }
        catch (\moodle_exception $e) { $this->assertSame('invaliddeclaration', $e->errorcode); }
        $this->assertFalse(access::approved($user->id));
    }
    public function test_student_cannot_manage_approvals(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        access::grant($user->id, 'blind', true);
    }
    public function test_approval_requires_a_live_unsuspended_account(): void {
        global $DB;
        $user = $this->user();
        $this->assertTrue(access::allowed($user->id));
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
        $this->assertFalse(access::allowed($user->id));
    }
    public function test_device_pairing_is_single_use_hashed_and_never_authenticates(): void {
        global $DB, $USER;
        $user = $this->user();
        $before = $USER->id;
        $pair = $this->pair($user->id);
        $this->assertTrue(access::public_allowed());
        $this->assertEquals($before, $USER->id);
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_device', ['tokenhash' => $pair]));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_device', ['tokenhash' => $_COOKIE[access::cookie_name()]]));
        $this->expectException(\moodle_exception::class);
        access::claim_pair($pair);
    }
    public function test_expired_or_fabricated_device_is_rejected(): void {
        global $DB;
        $user = $this->user();
        $this->pair($user->id);
        $DB->set_field('lumen10voiceaccess_device', 'expires', time() - 1, ['userid' => $user->id]);
        $this->assertFalse(access::public_allowed());
        $_COOKIE[access::cookie_name()] = str_repeat('0', 64);
        $this->assertFalse(access::public_allowed());
    }
    public function test_revocation_invalidates_devices_and_reapproval_does_not_restore_them(): void {
        $user = $this->user();
        $this->pair($user->id);
        access::revoke($user->id);
        $this->assertFalse(access::allowed($user->id));
        $this->assertFalse(access::public_allowed());
        access::grant($user->id, 'severemobility', true);
        $this->assertTrue(access::allowed($user->id));
        $this->assertFalse(access::public_allowed());
    }
    public function test_device_revocation_preserves_account_approval(): void {
        $user = $this->user();
        $this->pair($user->id);
        access::revoke_devices($user->id);
        $this->assertTrue(access::allowed($user->id));
        $this->assertFalse(access::public_allowed());
    }
    public function test_captcha_exception_is_bound_to_the_same_approved_account(): void {
        global $CFG;
        $user = $this->user();
        $this->pair($user->id);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(0, $CFG->enableloginrecaptcha);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['username'] = $user->username;
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(0, $CFG->enableloginrecaptcha);
        $_POST['username'] = 'another-account';
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
        access::revoke($user->id);
        $_POST['username'] = $user->username;
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
    }
    public function test_conventional_choice_restores_captcha_even_for_a_paired_device(): void {
        global $CFG;
        $user = $this->user();
        $this->pair($user->id);
        $_COOKIE[login_policy::CONVENTIONAL_COOKIE] = '1';
        foreach (['GET', 'POST'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST['username'] = $user->username;
            $CFG->enableloginrecaptcha = 1;
            login_policy::configure(new \core\hook\after_config());
            $this->assertEquals(1, $CFG->enableloginrecaptcha);
            $this->assertFalse(login_policy::captcha_exempt());
        }
    }

    public function test_failed_login_keeps_captcha_visible_after_native_redirect(): void {
        global $CFG, $SESSION;
        $user = $this->user();
        $this->pair($user->id);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $SESSION->loginerrormsg = get_string('missingrecaptchachallengefield');
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
        $this->assertFalse(login_policy::captcha_exempt());
    }

    public function test_unlisted_ai_cannot_use_saved_credentials(): void {
        global $DB;
        $user = $this->user();
        preferences::save($user->id, preferences::defaults() + ['provider' => 'openai',
            'model' => 'gpt-6-luna', 'apikey' => 'synthetic-not-a-real-key', 'consent' => 1]);
        access::revoke($user->id);
        set_config('allowai', 1, preferences::COMPONENT);
        $this->assertSame(['intent' => 'UNKNOWN'], ai\router::interpret($user->id, 'show my grades'));
        $this->assertEquals(0, $DB->get_field('lumen10voiceaccess_user', 'quotaused', ['userid' => $user->id]));
    }
    public function test_duplicate_email_never_grants_an_ambiguous_captcha_exception(): void {
        global $CFG;
        $user = $this->user();
        $this->getDataGenerator()->create_user(['email' => $user->email]);
        $this->pair($user->id);
        $CFG->authloginviaemail = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['username'] = $user->email;
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
    }
    public function test_privacy_deletion_removes_approval_and_device_secrets(): void {
        global $DB;
        $user = $this->user();
        $other = $this->user();
        $this->pair($user->id);
        $this->pair($other->id);
        privacy\provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_access', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_device', ['userid' => $user->id]));
        $this->assertTrue(access::allowed($other->id));
    }
    public function test_email_matching_another_username_keeps_the_native_captcha(): void {
        global $CFG;
        $user = $this->user();
        $this->getDataGenerator()->create_user(['username' => $user->email]);
        $this->pair($user->id);
        $CFG->authloginviaemail = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['username'] = $user->email;
        $CFG->enableloginrecaptcha = 1;
        login_policy::configure(new \core\hook\after_config());
        $this->assertEquals(1, $CFG->enableloginrecaptcha);
    }
    public function test_session_scope_rejects_another_approved_account_and_guest_transition(): void {
        $a = $this->user();
        $b = $this->user();
        $this->pair($a->id);
        $this->setUser($a);
        $this->assertTrue(access::session_allowed((string)$a->id));
        $this->assertFalse(access::session_allowed('login'));
        $this->setUser($b);
        $this->assertTrue(access::allowed($b->id));
        $this->assertFalse(access::session_allowed((string)$a->id));
        $this->setGuestUser();
        $this->assertTrue(access::session_allowed('login'));
        $this->assertFalse(access::session_allowed((string)$a->id));
    }
    public function test_deleted_approval_cannot_be_recreated_by_a_stale_preferences_save(): void {
        global $DB;
        $user = $this->user();
        access::erase_user_data($user->id);
        try {
            preferences::save($user->id, preferences::defaults() + ['provider' => 'none', 'model' => 'gpt-6-luna']);
            $this->fail('Stale preference save was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('notapproved', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_user', ['userid' => $user->id]));
    }
    public function test_delete_authorization_removes_access_and_links_but_preserves_account_and_preferences(): void {
        global $DB;
        $user = $this->user();
        $other = $this->user();
        preferences::save($user->id, preferences::defaults() + ['provider' => 'openai',
            'model' => 'gpt-6-luna', 'apikey' => 'synthetic-preserved-key', 'consent' => 1]);
        $before = preferences::get($user->id);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->pair($user->id);
        access::set_voice_phrase($user->id, 'Synthetic delete approval phrase');
        $pending = access::issue_pair($user->id);
        access::issue_pair($other->id);
        $sink = $this->redirectEvents();
        $this->assertTrue(access::delete_authorization($user->id));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_access', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_device', ['userid' => $user->id]));
        $this->assertFalse(access::allowed($user->id));
        $this->assertFalse(access::public_allowed());
        $this->assertFalse(access::has_voice_phrase($user->id));
        $this->assertEquals($before, preferences::get($user->id));
        $this->assertTrue($DB->record_exists('user', ['id' => $user->id, 'deleted' => 0]));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        $this->assertTrue(access::allowed($other->id));
        $this->assertTrue($DB->record_exists('lumen10voiceaccess_device', ['userid' => $other->id]));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertSame('deleteapproval', $events[0]->other['action']);
        $this->assertEquals($user->id, $events[0]->relateduserid);
        $sink->close();
        $this->expectException(\moodle_exception::class);
        access::claim_pair($pending);
    }

    public function test_delete_authorization_supports_revoked_entries_and_repeated_requests(): void {
        global $DB;
        $user = $this->user();
        access::revoke($user->id);
        $this->assertTrue(access::delete_authorization($user->id));
        $this->assertFalse(access::delete_authorization($user->id));
        $this->assertFalse($DB->record_exists('lumen10voiceaccess_access', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('user', ['id' => $user->id]));
    }

    public function test_delete_authorization_requires_management_capability(): void {
        global $DB;
        $user = $this->user();
        $this->setUser($this->getDataGenerator()->create_user());
        try {
            access::delete_authorization($user->id);
            $this->fail('An ordinary account deleted an approval');
        } catch (\required_capability_exception $e) {
            $this->assertTrue($DB->record_exists('lumen10voiceaccess_access', ['userid' => $user->id]));
        }
    }

    public function test_cleanup_removes_only_expired_and_used_tokens(): void {
        global $DB;
        $user = $this->user();
        $this->pair($user->id);
        $this->assertEquals(2, $DB->count_records('lumen10voiceaccess_device', ['userid' => $user->id]));
        (new task\purge_devices())->execute();
        $this->assertEquals(1, $DB->count_records('lumen10voiceaccess_device', ['userid' => $user->id]));
        $this->assertTrue(access::public_allowed());
    }
}
