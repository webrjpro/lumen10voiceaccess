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
 * classes/access.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** Explicit individual approval for participants; site administrators also have diagnostic access. */
final class access {
    public const DECLARATIONS = ['blind', 'severemobility', 'both'];
    public const COOKIE_DAYS = 90;

    private static function user_lock(int $userid): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory(preferences::COMPONENT)->get_lock('user-' . $userid, 5);
        if (!$lock) {
            throw new \moodle_exception('pairinvalid', preferences::COMPONENT);
        }
        return $lock;
    }

    public static function approved(int $userid): bool {
        global $DB, $CFG;
        if ($userid <= 0 || $userid === (int)($CFG->siteguest ?? 0)) {
            return false;
        }
        return $DB->record_exists_sql('SELECT a.id FROM {lumen10voiceaccess_access} a JOIN {user} u ON u.id=a.userid
            WHERE a.userid=:userid AND a.active=1 AND u.deleted=0 AND u.suspended=0
            AND a.declaration IN (:blind,:mobility,:both)',
            ['userid' => $userid, 'blind' => 'blind', 'mobility' => 'severemobility', 'both' => 'both']);
    }

    public static function allowed(int $userid): bool {
        if (!get_config(preferences::COMPONENT, 'enabled')) {
            return false;
        }
        if (is_siteadmin($userid)) {
            return true;
        }
        return self::approved($userid) &&
            has_capability('mod/lumen10voiceaccess:use', \context_system::instance(), $userid);
    }

    public static function require_use(): void {
        global $USER;
        require_capability('mod/lumen10voiceaccess:use', \context_system::instance());
        if (!self::allowed((int)$USER->id)) {
            throw new \moodle_exception('notapproved', preferences::COMPONENT);
        }
    }

    public static function grant(int $userid, string $declaration, bool $confirmed): void {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $lock = self::user_lock($userid);
        try {
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
        if (!$confirmed || !in_array($declaration, self::DECLARATIONS, true) || isguestuser($user)) {
            throw new \moodle_exception('invaliddeclaration', preferences::COMPONENT);
        }
        $record = $DB->get_record('lumen10voiceaccess_access', ['userid' => $userid]);
        $now = time();
        if ($record) {
            $DB->update_record('lumen10voiceaccess_access', (object)['id' => $record->id, 'declaration' => $declaration,
                'active' => 1, 'timemodified' => $now]);
        } else {
            $DB->insert_record('lumen10voiceaccess_access', (object)['userid' => $userid, 'declaration' => $declaration,
                'active' => 1, 'timecreated' => $now, 'timemodified' => $now]);
        }
        \mod_lumen10voiceaccess\event\access_updated::create(['context' => \context_system::instance(),
            'relateduserid' => $userid, 'other' => ['action' => 'grant']])->trigger();
        } finally {
            $lock->release();
        }
    }

    public static function revoke(int $userid): void {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $lock = self::user_lock($userid);
        try {
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('lumen10voiceaccess_access', 'active', 0, ['userid' => $userid]);
        operations::revoke($userid);
        $DB->set_field('lumen10voiceaccess_access', 'timemodified', time(), ['userid' => $userid]);
        $DB->delete_records('lumen10voiceaccess_device', ['userid' => $userid]);
        $transaction->allow_commit();
        \mod_lumen10voiceaccess\event\access_updated::create(['context' => \context_system::instance(),
            'relateduserid' => $userid, 'other' => ['action' => 'revoke']])->trigger();
        } finally {
            $lock->release();
        }
    }

    /** Remove the list entry and all login/device links, preserving the Moodle account and preferences. */
    public static function delete_authorization(int $userid): bool {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $lock = self::user_lock($userid);
        try {
            if (!$DB->record_exists('lumen10voiceaccess_access', ['userid' => $userid])) {
                return false;
            }
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records('lumen10voiceaccess_device', ['userid' => $userid]);
            $DB->delete_records('lumen10voiceaccess_access', ['userid' => $userid]);
            operations::revoke($userid);
            $transaction->allow_commit();
            \mod_lumen10voiceaccess\event\access_updated::create([
                'context' => \context_system::instance(), 'relateduserid' => $userid,
                'other' => ['action' => 'deleteapproval'],
            ])->trigger();
            return true;
        } finally {
            $lock->release();
        }
    }

    public static function revoke_devices(int $userid): void {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $lock = self::user_lock($userid);
        try {
        $DB->delete_records('lumen10voiceaccess_device', ['userid' => $userid]);
        \mod_lumen10voiceaccess\event\access_updated::create(['context' => \context_system::instance(),
            'relateduserid' => $userid, 'other' => ['action' => 'devices']])->trigger();
        } finally {
            $lock->release();
        }
    }

    public static function issue_pair(int $userid): string {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $lock = self::user_lock($userid);
        try {
        if (!self::approved($userid)) {
            throw new \moodle_exception('notapproved', preferences::COMPONENT);
        }
        $DB->delete_records('lumen10voiceaccess_device', ['userid' => $userid, 'kind' => 'pair']);
        $token = bin2hex(random_bytes(32));
        $DB->insert_record('lumen10voiceaccess_device', (object)['userid' => $userid, 'tokenhash' => hash('sha256', $token),
            'kind' => 'pair', 'expires' => time() + HOURSECS, 'used' => 0, 'timecreated' => time()]);
        return $token;
        } finally {
            $lock->release();
        }
    }

    /** Consume once under a shared native lock; returns only the new device credential. */
    public static function claim_pair(string $token): string {
        global $DB;
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !get_config(preferences::COMPONENT, 'enabled') ||
                !get_config(preferences::COMPONENT, 'publiclogin')) {
            throw new \moodle_exception('pairinvalid', preferences::COMPONENT);
        }
        $hash = hash('sha256', $token);
        $lock = \core\lock\lock_config::get_lock_factory(preferences::COMPONENT)->get_lock('pair-' . $hash, 5);
        if (!$lock) {
            throw new \moodle_exception('pairinvalid', preferences::COMPONENT);
        }
        $userlock = null;
        $transaction = null;
        try {
            $record = $DB->get_record('lumen10voiceaccess_device', ['tokenhash' => $hash, 'kind' => 'pair', 'used' => 0]);
            if (!$record) {
                throw new \moodle_exception('pairinvalid', preferences::COMPONENT);
            }
            $userlock = self::user_lock((int)$record->userid);
            $record = $DB->get_record('lumen10voiceaccess_device', ['tokenhash' => $hash, 'kind' => 'pair', 'used' => 0]);
            if (!$record || (int)$record->expires <= time() || !self::allowed((int)$record->userid)) {
                throw new \moodle_exception('pairinvalid', preferences::COMPONENT);
            }
            $transaction = $DB->start_delegated_transaction();
            $DB->set_field('lumen10voiceaccess_device', 'used', 1, ['id' => $record->id]);
            $device = bin2hex(random_bytes(32));
            $DB->insert_record('lumen10voiceaccess_device', (object)['userid' => $record->userid,
                'tokenhash' => hash('sha256', $device), 'kind' => 'device',
                'expires' => time() + self::COOKIE_DAYS * DAYSECS, 'used' => 0, 'timecreated' => time()]);
            $transaction->allow_commit();
            return $device;
        } catch (\Throwable $e) {
            if ($transaction) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            if ($userlock) {
                $userlock->release();
            }
            $lock->release();
        }
    }

    public static function cookie_name(): string {
        global $CFG;
        return 'L10VADevice' . substr(hash('sha256', $CFG->wwwroot), 0, 10);
    }

    public static function device_userid(): int {
        global $DB;
        if (!get_config(preferences::COMPONENT, 'enabled') || !get_config(preferences::COMPONENT, 'publiclogin')) {
            return 0;
        }
        $token = $_COOKIE[self::cookie_name()] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return 0;
        }
        $record = $DB->get_record('lumen10voiceaccess_device', ['tokenhash' => hash('sha256', $token), 'kind' => 'device', 'used' => 0]);
        return $record && (int)$record->expires > time() && self::allowed((int)$record->userid) ? (int)$record->userid : 0;
    }

    public static function public_allowed(): bool {
        return self::device_userid() > 0;
    }

    public static function session_allowed(string $owner = ''): bool {
        global $USER;
        if (isloggedin() && !isguestuser()) {
            return ($owner === '' || $owner === (string)$USER->id) && self::allowed((int)$USER->id);
        }
        return ($owner === '' || $owner === 'login') && self::public_allowed();
    }

    public static function normalize_phrase(string $phrase): string {
        $text = \core_text::strtolower(trim($phrase));
        $text = preg_replace('/^(?:a minha frase [eé]|minha frase [eé]|frase cadastrada|frase)\s+/u', '', $text);
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        return preg_replace('/\s+/', ' ', trim($text));
    }

    public static function set_voice_phrase(int $userid, string $phrase): void {
        global $DB;
        require_capability('mod/lumen10voiceaccess:manage', \context_system::instance());
        $norm = self::normalize_phrase($phrase);
        if (\core_text::strlen($norm) < 4) {
            return;
        }
        $lock = self::user_lock($userid);
        try {
            if (!self::approved($userid)) {
                throw new \moodle_exception('notapproved', preferences::COMPONENT);
            }
            $hash = hash('sha256', 'l10va-phrase:' . $norm);
            $existing = $DB->get_record('lumen10voiceaccess_device', ['tokenhash' => $hash]);
            if ($existing && (int)$existing->userid !== $userid) {
                throw new \moodle_exception('invalidrequest');
            }
            $DB->delete_records('lumen10voiceaccess_device', ['userid' => $userid, 'kind' => 'phrase']);
            $DB->insert_record('lumen10voiceaccess_device', (object)[
                'userid' => $userid,
                'tokenhash' => $hash,
                'kind' => 'phrase',
                'expires' => time() + 3650 * DAYSECS,
                'used' => 0,
                'timecreated' => time(),
            ]);
        } finally {
            $lock->release();
        }
    }

    public static function has_voice_phrase(int $userid): bool {
        global $DB;
        return $DB->record_exists_select(
            'lumen10voiceaccess_device',
            'userid = :userid AND kind = :kind AND used = 0 AND expires > :now',
            ['userid' => $userid, 'kind' => 'phrase', 'now' => time()]
        );
    }

    public static function verify_voice_phrase(string $phrase): int {
        global $DB;
        if (!get_config(preferences::COMPONENT, 'enabled') || !get_config('mod_lumen10voiceaccess', 'version')) {
            return 0;
        }
        $norm = self::normalize_phrase($phrase);
        if (\core_text::strlen($norm) < 4) {
            return 0;
        }
        $hash = hash('sha256', 'l10va-phrase:' . $norm);
        $record = $DB->get_record('lumen10voiceaccess_device', ['tokenhash' => $hash, 'kind' => 'phrase', 'used' => 0]);
        if (!$record || (int)$record->expires <= time() || !self::allowed((int)$record->userid)) {
            return 0;
        }
        return (int)$record->userid;
    }

    public static function issue_device_cookie(int $userid): string {
        global $DB;
        $device = bin2hex(random_bytes(32));
        $DB->insert_record('lumen10voiceaccess_device', (object)[
            'userid' => $userid,
            'tokenhash' => hash('sha256', $device),
            'kind' => 'device',
            'expires' => time() + self::COOKIE_DAYS * DAYSECS,
            'used' => 0,
            'timecreated' => time(),
        ]);
        return $device;
    }

    /** Privacy deletion shares the same lock as device creation and preference saves. */
    public static function erase_user_data(int $userid): void {
        global $DB;
        $lock = self::user_lock($userid);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach (['lumen10voiceaccess_user', 'lumen10voiceaccess_access', 'lumen10voiceaccess_device', 'lumen10voiceaccess_aiusage',
                    'lumen10voiceaccess_live'] as $table) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }
}
