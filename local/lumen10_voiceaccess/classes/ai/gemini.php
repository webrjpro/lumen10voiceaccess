<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess\ai;
defined('MOODLE_INTERNAL') || die();

use local_lumen10_voiceaccess\preferences;

/**
 * Institutional Google Gemini provider (Lumen10 Acesso Universal).
 *
 * The API key is read only from the server environment, config.php or a protected file
 * outside the web root. It is never stored in the database,
 * never rendered in HTML/JavaScript and never written to logs.
 */
final class gemini {
    public const PROVIDER = 'gemini';
    public const ENV = 'LUMEN10_GEMINI_API_KEY';
    public const FILE_ENV = 'LUMEN10_GEMINI_API_KEY_FILE';
    public const CFGKEY = 'local_lumen10_voiceaccess_geminikey';
    public const DEFAULT_TEXT_MODEL = 'gemini-3.5-flash-lite';
    public const DEFAULT_LIVE_MODEL = 'gemini-3.8-live';
    public const DEFAULT_PROJECT_DAILY = 450;
    public const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    public const TABLE = 'local_l10va_aiusage';

    /** Server-side secret. Callers must never echo, log or serialise the returned value. */
    private static function api_key(): string {
        global $CFG;
        foreach ([getenv(self::ENV), $_SERVER[self::ENV] ?? null, $_ENV[self::ENV] ?? null,
                $CFG->{self::CFGKEY} ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }
        $path = self::key_file();
        // A server-owned file outside the web root also works when web and CLI load
        // different Moodle config files. No UI field may select or overwrite it.
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $value = @file_get_contents($path, false, null, 0, 513);
        return is_string($value) ? trim($value) : '';
    }

    /** Fixed, server-only provisioning endpoint; the main API credential never reaches a client. */
    public static function issue_live_token(array $setup, int $seconds): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!self::configured()) {
            throw new \moodle_exception('providererror', preferences::COMPONENT);
        }
        $seconds = max(60, min(300, $seconds));
        $now = time();
        $body = ['uses' => 1, 'expireTime' => gmdate('Y-m-d\TH:i:s\Z', $now + $seconds),
            'newSessionExpireTime' => gmdate('Y-m-d\TH:i:s\Z', $now + min(60, $seconds - 5)),
            // Empty fieldMask locks the complete effective setup to this server-authored object.
            'bidiGenerateContentSetup' => $setup];
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader(['Content-Type: application/json', 'x-goog-api-key: ' . self::api_key()]);
        $response = $curl->post('https://generativelanguage.googleapis.com/v1beta/auth_tokens',
            json_encode($body), [
                'CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_FOLLOWLOCATION' => false, 'CURLOPT_MAXREDIRS' => 0,
                'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2,
            ]);
        $info = $curl->get_info();
        if ((int)($info['http_code'] ?? 0) === 429) {
            throw new \moodle_exception('ratelimited', preferences::COMPONENT);
        }
        if ($curl->get_errno() || (int)($info['http_code'] ?? 0) !== 200 ||
                !is_string($response) || strlen($response) > 60000) {
            throw new \moodle_exception('providererror', preferences::COMPONENT);
        }
        $data = json_decode($response, true);
        $token = is_array($data) ? ($data['name'] ?? null) : null;
        if (!is_string($token) || !str_starts_with($token, 'auth_tokens/') ||
                preg_match('/[\x00-\x20\x7f]/', $token) || strlen($token) > 50000) {
            throw new \moodle_exception('providererror', preferences::COMPONENT);
        }
        return $token;
    }

    private static function key_file(): string {
        global $CFG;
        $configured = getenv(self::FILE_ENV);
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }
        return $CFG->dataroot . '/local_lumen10_voiceaccess/secrets/gemini.key';
    }

    public static function configured(): bool {
        $key = self::api_key();
        return $key !== '' && strlen($key) <= 512 && !preg_match('/[\s]/', $key);
    }

    /** Masked hint for administrators: only the last four characters. */
    public static function masked_key(): string {
        $key = self::api_key();
        return $key === '' ? '' : str_repeat('•', 8) . substr($key, -4);
    }

    public static function key_source(): string {
        global $CFG;
        if (is_string(getenv(self::ENV)) && trim((string)getenv(self::ENV)) !== '') {
            return 'env';
        }
        if (!empty($_SERVER[self::ENV]) || !empty($_ENV[self::ENV])) {
            return 'env';
        }
        if (!empty($CFG->{self::CFGKEY})) {
            return 'config';
        }
        return is_readable(self::key_file()) && self::api_key() !== '' ? 'file' : 'none';
    }

    public static function enabled(): bool {
        return (bool)get_config(preferences::COMPONENT, 'gemini_enabled') && self::configured();
    }

    public static function tier(): string {
        return get_config(preferences::COMPONENT, 'gemini_tier') === 'paid' ? 'paid' : 'free';
    }

    public static function text_model(): string {
        $model = trim((string)get_config(preferences::COMPONENT, 'gemini_textmodel'));
        return self::valid_model($model) ? $model : self::DEFAULT_TEXT_MODEL;
    }

    public static function live_model(): string {
        $model = trim((string)get_config(preferences::COMPONENT, 'gemini_livemodel'));
        return self::valid_model($model) ? $model : self::DEFAULT_LIVE_MODEL;
    }

    public static function valid_model(string $model): bool {
        return (bool)preg_match('/^[a-z0-9][a-z0-9.-]{1,99}$/D', $model);
    }

    public static function project_daily_limit(): int {
        $raw = get_config(preferences::COMPONENT, 'gemini_projectdaily');
        return max(0, min(100000, $raw === false || $raw === '' ? self::DEFAULT_PROJECT_DAILY : (int)$raw));
    }

    /** Usernames allowed while the project is on the free tier (Google may use free-tier data). */
    public static function pilot_usernames(): array {
        $raw = (string)get_config(preferences::COMPONENT, 'gemini_pilotusers');
        $names = preg_split('/[\s,;]+/u', \core_text::strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique($names));
    }

    /** 'pilot' = only listed usernames; 'all' = every person approved for Universal Access. */
    public static function audience(): string {
        return get_config(preferences::COMPONENT, 'gemini_audience') === 'all' ? 'all' : 'pilot';
    }

    /** Prebuilt Gemini voices offered to students. Female voices first; Sulafat is the default. */
    public const VOICES = [
        'Sulafat' => 'female', 'Aoede' => 'female', 'Callirrhoe' => 'female', 'Kore' => 'female',
        'Achird' => 'male', 'Charon' => 'male',
    ];
    public const DEFAULT_VOICE = 'Sulafat';

    public static function default_voice(): string {
        $voice = (string)get_config(preferences::COMPONENT, 'gemini_voice');
        return array_key_exists($voice, self::VOICES) ? $voice : self::DEFAULT_VOICE;
    }

    /** Whether this person may have phrases interpreted by the institutional Gemini provider. */
    public static function eligible(int $userid): bool {
        global $DB;
        if ($userid <= 0 || !self::enabled() || !get_config(preferences::COMPONENT, 'allowai')) {
            return false;
        }
        if (is_siteadmin($userid)) {
            return true;
        }
        if (self::audience() === 'all') {
            return true;
        }
        $pilot = self::pilot_usernames();
        if (!$pilot) {
            return false;
        }
        $username = $DB->get_field('user', 'username', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        return is_string($username) && in_array(\core_text::strtolower($username), $pilot, true);
    }

    public static function today(): string {
        return gmdate('Y-m-d');
    }

    public static function project_used_today(): int {
        global $DB;
        return (int)$DB->get_field_sql('SELECT COALESCE(SUM(requests), 0) FROM {' . self::TABLE . '}
            WHERE day = ? AND provider = ?', [self::today(), self::PROVIDER]);
    }

    /** Aggregated consumption only: never the phrase, the response, audio or headers. */
    public static function record_usage(int $userid, string $model, bool $ok, int $prompttokens, int $outputtokens): int {
        global $DB;
        $day = self::today();
        $params = ['day' => $day, 'userid' => $userid, 'provider' => self::PROVIDER, 'model' => $model];
        $row = $DB->get_record(self::TABLE, $params);
        if ($row) {
            $DB->execute('UPDATE {' . self::TABLE . '} SET requests = requests + 1, errors = errors + ?,
                prompttokens = prompttokens + ?, outputtokens = outputtokens + ?, timemodified = ? WHERE id = ?',
                [$ok ? 0 : 1, max(0, $prompttokens), max(0, $outputtokens), time(), $row->id]);
            return (int)$row->id;
        }
        return (int)$DB->insert_record(self::TABLE, (object)($params + [
            'requests' => 1, 'errors' => $ok ? 0 : 1,
            'prompttokens' => max(0, $prompttokens), 'outputtokens' => max(0, $outputtokens), 'timemodified' => time(),
        ]));
    }

    /** Daily totals for the administration status page. */
    public static function usage_summary(int $days = 7): array {
        global $DB;
        $since = gmdate('Y-m-d', time() - max(0, $days - 1) * DAYSECS);
        return array_values($DB->get_records_sql('SELECT day, SUM(requests) AS requests, SUM(errors) AS errors,
                SUM(prompttokens) AS prompttokens, SUM(outputtokens) AS outputtokens, COUNT(DISTINCT userid) AS users
            FROM {' . self::TABLE . '} WHERE provider = ? AND day >= ? GROUP BY day ORDER BY day DESC',
            [self::PROVIDER, $since]));
    }

    /**
     * Classify a phrase with the configured text model. Returns raw model text plus token usage.
     *
     * @throws \moodle_exception ratelimited|providererror
     */
    public static function generate(string $instructions, string $input, array $intents, ?string $model = null,
            int $timeout = 15, int $userid = 0): array {
        global $DB;
        $model = $model ?? self::text_model();
        if (!self::configured() || !self::valid_model($model)) {
            throw new \moodle_exception('providererror', preferences::COMPONENT);
        }
        // Reserve before network I/O under a shared lock. Different users and admin tests
        // must not all pass the final available project slot at once.
        $lock = \core\lock\lock_config::get_lock_factory(preferences::COMPONENT)->get_lock('gemini-project', 5);
        if (!$lock) {
            throw new \moodle_exception('ratelimited', preferences::COMPONENT);
        }
        try {
            if (self::project_used_today() >= self::project_daily_limit()) {
                throw new \moodle_exception('ratelimited', preferences::COMPONENT);
            }
            $usageid = self::record_usage($userid, $model, true, 0, 0);
        } finally {
            $lock->release();
        }
        try {
            $result = self::generate_response($instructions, $input, $intents, $model, $timeout);
        } catch (\Throwable $e) {
            $DB->execute('UPDATE {' . self::TABLE . '} SET errors = errors + 1, timemodified = ? WHERE id = ?',
                [time(), $usageid]);
            throw $e;
        }
        // Atomic increments avoid losing totals when requests finish simultaneously.
        // Updating by id also avoids recreating records removed by a privacy request.
        $DB->execute('UPDATE {' . self::TABLE . '} SET prompttokens = prompttokens + ?,
            outputtokens = outputtokens + ?, timemodified = ? WHERE id = ?',
            [max(0, $result['prompttokens']), max(0, $result['outputtokens']), time(), $usageid]);
        return $result;
    }

    private static function generate_response(string $instructions, string $input, array $intents, string $model,
            int $timeout): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $generation = [
            'responseMimeType' => 'application/json', 'maxOutputTokens' => 128, 'temperature' => 0,
            'responseSchema' => ['type' => 'OBJECT', 'properties' => [
                'intent' => ['type' => 'STRING', 'enum' => array_values($intents)],
                'confidence' => ['type' => 'NUMBER'],
            ], 'required' => ['intent', 'confidence']],
        ];
        if (str_starts_with($model, 'gemini-3')) {
            $generation['thinkingConfig'] = ['thinkingLevel' => 'minimal'];
        }
        $body = [
            'systemInstruction' => ['parts' => [['text' => $instructions]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $input]]]],
            'generationConfig' => $generation,
        ];
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader(['Content-Type: application/json', 'x-goog-api-key: ' . self::api_key()]);
        // Fixed Google endpoint + no redirects: the key can never be forwarded to another host.
        $response = $curl->post(self::ENDPOINT . rawurlencode($model) . ':generateContent', json_encode($body), [
            'CURLOPT_TIMEOUT' => max(3, min(25, $timeout)), 'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_FOLLOWLOCATION' => false, 'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2,
        ]);
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        if ($status === 429) {
            throw new \moodle_exception('ratelimited', preferences::COMPONENT);
        }
        if ($curl->get_errno() || $status !== 200 || !is_string($response) || strlen($response) > 100000) {
            throw new \moodle_exception('providererror', preferences::COMPONENT, '', null, 'gemini http ' . $status);
        }
        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new \moodle_exception('providererror', preferences::COMPONENT);
        }
        $text = '';
        foreach ($result['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        $usage = is_array($result['usageMetadata'] ?? null) ? $result['usageMetadata'] : [];
        return [
            'text' => $text,
            'prompttokens' => (int)($usage['promptTokenCount'] ?? 0),
            'outputtokens' => (int)($usage['candidatesTokenCount'] ?? 0) + (int)($usage['thoughtsTokenCount'] ?? 0),
        ];
    }

    /** Administrative connection test with a fixed synthetic phrase (no personal data). */
    public static function test_connection(): array {
        $start = microtime(true);
        try {
            $result = self::generate(
                'Classify an accessibility navigation request in Portuguese. Return ONLY JSON with intent and confidence.',
                'abrir meus cursos', ['OPEN_COURSES', 'UNKNOWN'], null, 15);
            $parsed = json_decode(trim($result['text']), true);
            $ok = is_array($parsed) && ($parsed['intent'] ?? '') === 'OPEN_COURSES';
            return ['ok' => $ok, 'ms' => (int)round((microtime(true) - $start) * 1000),
                'detail' => $ok ? 'OPEN_COURSES' : 'unexpected'];
        } catch (\Throwable $e) {
            $code = $e instanceof \moodle_exception ? $e->errorcode : 'error';
            return ['ok' => false, 'ms' => (int)round((microtime(true) - $start) * 1000), 'detail' => $code];
        }
    }
}
