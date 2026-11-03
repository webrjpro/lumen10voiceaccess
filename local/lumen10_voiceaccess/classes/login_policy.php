<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** CAPTCHA exception bound to an approved account and a server-validated private device. */
final class login_policy {
    public const CONVENTIONAL_COOKIE = 'L10VAConventional';
    private static bool $captchaexempt = false;

    public static function captcha_exempt(): bool {
        return self::$captchaexempt;
    }

    public static function configure(\core\hook\after_config $hook): void {
        global $CFG, $DB, $SESSION;
        self::$captchaexempt = false;
        if (during_initial_install() || (defined('PHPUNIT_UTIL') && PHPUNIT_UTIL)) {
            return;
        }
        // A conventional-login choice only restores CAPTCHA; it never grants an exception.
        // After an unsuccessful POST, keep the challenge visible on Moodle's redirect GET.
        if (($_COOKIE[self::CONVENTIONAL_COOKIE] ?? '') === '1' ||
                (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($SESSION->loginerrormsg))) {
            return;
        }
        if ((int)get_config(preferences::COMPONENT, 'version') >= 2026101601 &&
                get_config(preferences::COMPONENT, 'enabled') &&
                get_config(preferences::COMPONENT, 'loginwithoutcaptcha')) {
            // All other users retain the native CAPTCHA, including requests made from a paired device.
            $userid = access::device_userid();
            if (!$userid) {
                return;
            }
            $user = $DB->get_record('user', ['id' => $userid], 'id,username,email,mnethostid', MUST_EXIST);
            if ((int)$user->mnethostid !== (int)$CFG->mnet_localhost_id) {
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $username = \core_text::strtolower(trim(optional_param('username', '', PARAM_RAW)));
                // Match Moodle's username-first lookup; an email may also be another person's username.
                $resolved = $DB->get_record('user', ['username' => $username,
                    'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0], 'id');
                if (!$resolved && !empty($CFG->authloginviaemail) && clean_param($username, PARAM_EMAIL)) {
                    $matches = $DB->get_records_select('user',
                        'LOWER(email)=:email AND mnethostid=:host AND deleted=0',
                        ['email' => $username, 'host' => $CFG->mnet_localhost_id], 'id', 'id', 0, 2);
                    if (count($matches) === 1) {
                        $resolved = reset($matches);
                    }
                }
                if (!$resolved || (int)$resolved->id !== $userid) {
                    return;
                }
            }
            // Only this request changes. Stored CAPTCHA, signup policy, MFA and tokens remain native.
            $CFG->enableloginrecaptcha = 0;
            self::$captchaexempt = true;
        }
    }
}
