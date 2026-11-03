<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** Only the current user's settings are exposed by the HTTP controllers. */
final class preferences {
    public const COMPONENT = 'local_lumen10_voiceaccess';
    public const DEFAULT_MODEL = 'gpt-6-luna';

    public static function defaults(): array {
        $base = ['spoken' => true, 'wakeword' => true, 'rate' => 1.0, 'contrast' => false,
            'large' => false, 'scaninterval' => 3, 'profile' => 'standard',
            'voiceuri' => '', 'pitch' => 1.0, 'volume' => 1.0, 'recognitionmode' => 'browser', 'resume' => true];
        $inst = get_config(self::COMPONENT, 'inst_prefs');
        if (is_string($inst) && $inst !== '') {
            $decoded = json_decode($inst, true);
            if (is_array($decoded)) {
                return array_intersect_key($decoded, $base) + $base;
            }
        }
        return $base;
    }

    public static function get(int $userid): \stdClass {
        global $DB;
        return $DB->get_record('local_lumen10_voiceaccess_user', ['userid' => $userid]) ?: (object)[
            'userid' => $userid, 'preferences' => json_encode(self::defaults()),
            'provider' => 'none', 'model' => self::DEFAULT_MODEL, 'apikey' => null,
            'consent' => 0, 'quotaused' => 0, 'quotaday' => '', 'lastrequest' => 0, 'timemodified' => 0,
        ];
    }

    public static function effective_ai(\stdClass $row): \stdClass {
        $eff = clone $row;
        // Administrative provider availability never replaces the person's permission to send phrases.
        if (empty($eff->consent)) {
            return $eff;
        }
        if (!empty($eff->apikey) && in_array($eff->provider, ['openai', 'anthropic'], true) && !empty($eff->consent)) {
            return $eff;
        }
        if (!empty($eff->userid) && ai\gemini::eligible((int)$eff->userid)) {
            // Marker only: the Gemini secret lives in the server environment and is never copied here.
            $eff->provider = ai\gemini::PROVIDER;
            $eff->model = ai\gemini::text_model();
            $eff->apikey = 'server-env';
            return $eff;
        }
        $instprovider = (string)get_config(self::COMPONENT, 'inst_provider');
        $instkey = (string)get_config(self::COMPONENT, 'inst_apikey');
        $instmodel = (string)get_config(self::COMPONENT, 'inst_model') ?: self::DEFAULT_MODEL;
        if ($instkey !== '' && in_array($instprovider, ['openai', 'anthropic'], true)) {
            $eff->provider = $instprovider;
            $eff->model = $instmodel;
            $eff->apikey = $instkey;
        }
        return $eff;
    }

    public static function save_institutional(array $data): void {
        global $DB;
        require_capability('local/lumen10_voiceaccess:manage', \context_system::instance());
        set_config('enabled', !empty($data['enabled']) ? 1 : 0, self::COMPONENT);
        set_config('loginvoicegate', !empty($data['loginvoicegate']) ? 1 : 0, self::COMPONENT);
        // Managing accessibility approvals does not grant control of institutional AI or spending.
        $canconfigureai = has_capability('moodle/site:config', \context_system::instance());
        if ($canconfigureai && array_key_exists('allowai', $data)) {
            set_config('allowai', !empty($data['allowai']) ? 1 : 0, self::COMPONENT);
        }
        if ($canconfigureai && array_key_exists('dailyquota', $data)) {
            set_config('dailyquota', max(0, min(1000, (int)$data['dailyquota'])), self::COMPONENT);
        }
        $prefs = [
            'spoken' => !empty($data['spoken']),
            'wakeword' => !empty($data['wakeword']),
            'rate' => max(0.5, min(2.0, (float)($data['rate'] ?? 1.0))),
            'contrast' => !empty($data['contrast']),
            'large' => !empty($data['large']),
            'scaninterval' => max(2, min(10, (int)($data['scaninterval'] ?? 3))),
            'profile' => in_array($data['profile'] ?? '', ['standard', 'screenreader', 'handsfree'], true)
                ? $data['profile'] : 'standard',
            'voiceuri' => \core_text::substr(trim($data['voiceuri'] ?? ''), 0, 255),
            'pitch' => max(0.5, min(2.0, (float)($data['pitch'] ?? 1.0))),
            'volume' => max(0.1, min(1.0, (float)($data['volume'] ?? 1.0))),
            'recognitionmode' => ($data['recognitionmode'] ?? '') === 'device' ? 'device' : 'browser',
            'resume' => !empty($data['resume']),
        ];
        set_config('inst_prefs', json_encode($prefs), self::COMPONENT);
        // Legacy provider submissions are accepted only from site configuration administrators.
        // The voice-only management form omits these fields and must preserve existing settings.
        if ($canconfigureai && array_key_exists('provider', $data)) {
            $provider = in_array($data['provider'] ?? '', ['none', 'openai', 'anthropic'], true) ? $data['provider'] : 'none';
            $oldprovider = (string)get_config(self::COMPONENT, 'inst_provider');
            set_config('inst_provider', $provider, self::COMPONENT);
            $model = trim($data['model'] ?? self::DEFAULT_MODEL);
            if (preg_match('/^[a-zA-Z0-9._:\/-]{1,100}$/D', $model)) {
                set_config('inst_model', $model, self::COMPONENT);
            }
            if ($oldprovider !== $provider || !empty($data['removekey']) || $provider === 'none') {
                set_config('inst_apikey', '', self::COMPONENT);
            }
            $rawkey = trim($data['apikey'] ?? '');
            if ($rawkey !== '' && empty($data['removekey']) && $provider !== 'none' &&
                    !preg_match('/[\r\n]/', $rawkey) && strlen($rawkey) <= 512) {
                set_config('inst_apikey', \core\encryption::encrypt($rawkey), self::COMPONENT);
            }
        }
        foreach ($DB->get_records('local_lumen10_voiceaccess_user') as $u) {
            $u->preferences = json_encode($prefs);
            $u->timemodified = time();
            $DB->update_record('local_lumen10_voiceaccess_user', $u);
        }
    }

    public static function public_settings(\stdClass $row): array {
        return array_intersect_key((array)json_decode($row->preferences), self::defaults()) + self::defaults();
    }

    public static function save(int $userid, array $data): void {
        $lock = \core\lock\lock_config::get_lock_factory(self::COMPONENT)->get_lock('user-' . $userid, 5);
        if (!$lock) {
            throw new \moodle_exception('ratelimited', self::COMPONENT);
        }
        try {
            if (!access::allowed($userid)) {
                throw new \moodle_exception('notapproved', self::COMPONENT);
            }
            self::write($userid, $data);
        } finally {
            $lock->release();
        }
    }

    private static function write(int $userid, array $data): void {
        global $DB;
        $row = self::get($userid);
        $row->preferences = json_encode([
            'spoken' => !empty($data['spoken']), 'wakeword' => !empty($data['wakeword']),
            'rate' => max(0.5, min(2.0, (float)$data['rate'])),
            'contrast' => !empty($data['contrast']), 'large' => !empty($data['large']),
            'scaninterval' => max(2, min(10, (int)$data['scaninterval'])),
            'profile' => in_array($data['profile'], ['standard', 'screenreader', 'handsfree'], true)
                ? $data['profile'] : 'standard',
            'voiceuri' => \core_text::substr(trim($data['voiceuri'] ?? ''), 0, 255),
            'pitch' => max(0.5, min(2.0, (float)($data['pitch'] ?? 1))),
            'volume' => max(0.1, min(1.0, (float)($data['volume'] ?? 1))),
            'recognitionmode' => ($data['recognitionmode'] ?? '') === 'device' ? 'device' : 'browser',
            'resume' => !empty($data['resume']),
        ]);
        $provider = in_array($data['provider'], ['none', 'openai', 'anthropic'], true) ? $data['provider'] : 'none';
        // Never reuse a credential with a different provider.
        if ($row->provider !== $provider || !empty($data['removekey'])) {
            $row->apikey = null;
        }
        $row->provider = $provider;
        $row->model = trim($data['model']);
        $row->consent = !empty($data['consent']) ? 1 : 0;
        if (!empty($data['apikey']) && empty($data['removekey']) && $provider !== 'none') {
            $row->apikey = \core\encryption::encrypt(trim($data['apikey']));
        }
        $row->timemodified = time();
        if (empty($row->id)) {
            $DB->insert_record('local_lumen10_voiceaccess_user', $row);
        } else {
            // Do not overwrite quota columns from a stale form read.
            unset($row->quotaused, $row->quotaday, $row->lastrequest);
            $DB->update_record('local_lumen10_voiceaccess_user', $row);
        }
    }
}
