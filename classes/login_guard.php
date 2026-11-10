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
 * classes/login_guard.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Bound anonymous phrase attempts and preserve Moodle account restrictions. */
final class login_guard {
    public const WINDOW = 900;
    public const ATTEMPTS = 10;

    /** Reserve a bounded attempt under a native lock; never retain the address or phrase. */
    public static function consume_attempt(string $address): bool {
        global $CFG;
        $key = hash_hmac('sha256', $address, (string)($CFG->passwordsaltmain ?? $CFG->siteidentifier));
        $lock = \core\lock\lock_config::get_lock_factory(preferences::COMPONENT)->get_lock('login-' . $key, 2);
        if (!$lock) {
            return false;
        }
        try {
            $cache = \cache::make(preferences::COMPONENT, 'login_attempts');
            $record = $cache->get($key);
            if (!$record || time() - $record['start'] >= self::WINDOW) {
                $record = ['start' => time(), 'count' => 0];
            }
            if ($record['count'] >= self::ATTEMPTS) {
                return false;
            }
            $record['count']++;
            return $cache->set($key, $record);
        } finally {
            $lock->release();
        }
    }

    /** A phrase does not override account lockout, SSO, suspension or administrator authentication. */
    public static function eligible(\stdClass $user): bool {
        global $CFG;
        require_once($CFG->libdir . '/authlib.php');
        return !empty($user->confirmed) && empty($user->deleted) && empty($user->suspended) &&
            (int)$user->mnethostid === (int)$CFG->mnet_localhost_id && !isguestuser($user) &&
            !is_siteadmin($user) && in_array($user->auth, ['manual', 'email'], true) &&
            is_enabled_auth($user->auth) && !login_is_lockedout($user) && access::allowed((int)$user->id);
    }
}
