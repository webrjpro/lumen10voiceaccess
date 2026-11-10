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
 * tests/login_guard_test.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Exercise account restrictions and shared anonymous throttling without external calls. */
final class login_guard_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, preferences::COMPONENT);
    }

    public function test_address_budget_is_shared_and_bounded(): void {
        for ($i = 0; $i < login_guard::ATTEMPTS; $i++) {
            $this->assertTrue(login_guard::consume_attempt('192.0.2.71'));
        }
        $this->assertFalse(login_guard::consume_attempt('192.0.2.71'));
        $this->assertTrue(login_guard::consume_attempt('192.0.2.72'));
    }

    public function test_lockout_suspension_sso_and_revocation_block_phrase_authentication(): void {
        global $CFG, $DB;
        $user = $this->getDataGenerator()->create_user();
        access::grant($user->id, 'blind', true);
        $this->assertTrue(login_guard::eligible($user));
        $CFG->lockoutthreshold = 3;
        $CFG->lockoutduration = 900;
        set_user_preference('login_lockout', time(), $user);
        unset($user->preference);
        $this->assertFalse(login_guard::eligible($user));
        login_unlock_account($user);
        $user->suspended = 1;
        $this->assertFalse(login_guard::eligible($user));
        $user->suspended = 0;
        $user->auth = 'oauth2';
        $this->assertFalse(login_guard::eligible($user));
        $user->auth = 'manual';
        access::revoke($user->id);
        $this->assertFalse(login_guard::eligible($user));
        $this->assertTrue($DB->record_exists('user', ['id' => $user->id]));
    }

    public function test_site_administrator_cannot_use_phrase_login(): void {
        $this->assertFalse(login_guard::eligible(get_admin()));
    }
}
