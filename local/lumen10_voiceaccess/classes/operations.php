<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Bounded operational ledger. No audio, transcripts, page contents, credentials or IP addresses. */
final class operations {
    public const TABLE = 'local_l10va_live';
    public const REASONS = ['stopped', 'navigation', 'hidden', 'local', 'expired', 'offline', 'limited',
        'unavailable', 'revoked', 'provider', 'abandoned'];

    public static function limit(): int {
        $value = get_config(preferences::COMPONENT, 'gemini_live_maxsessions');
        return max(1, min(1000, $value === false ? 10 : (int)$value));
    }

    /** Call only while holding the gemini-project lock; reservations include provisioning time. */
    public static function reserve(string $id, int $userid, int $usageid, int $expires): void {
        global $DB;
        $DB->insert_record(self::TABLE, (object)['sessionhash' => hash('sha256', $id), 'userid' => $userid,
            'usageid' => $usageid, 'timecreated' => time(), 'expires' => $expires, 'lastseen' => time(), 'reason' => 'pending']);
    }

    public static function active(): int {
        global $DB;
        return $DB->count_records_select(self::TABLE, 'ended = 0 AND expires > ?', [time()]);
    }

    /** Called under the same per-user lock used by report() and privacy deletion. */
    public static function revoke(int $userid): void {
        global $DB;
        $DB->execute('UPDATE {' . self::TABLE . '} SET ended = 1, reason = ?, lastseen = ?
            WHERE userid = ? AND ended = 0', ['revoked', time(), $userid]);
    }

    public static function reason(string $reason): string {
        return in_array($reason, self::REASONS, true) ? $reason : 'unavailable';
    }

    /** Operational statistics, never a billing statement or an accessibility certification. */
    public static function snapshot(int $hours = 24): array {
        global $DB;
        $hours = max(1, min(720, $hours));
        $since = time() - $hours * HOURSECS;
        $summary = $DB->get_record_sql('SELECT COUNT(*) AS sessions,
            SUM(CASE WHEN connectms > 0 THEN 1 ELSE 0 END) AS connected,
            SUM(CASE WHEN reason IN (\'offline\',\'limited\',\'unavailable\',\'provider\') THEN 1 ELSE 0 END) AS failures,
            SUM(CASE WHEN ended = 0 AND expires <= ? THEN 1 ELSE 0 END) AS unclosed,
            SUM(actions) AS actions, SUM(actionerrors) AS actionerrors, SUM(rejected) AS rejected,
            SUM(inputtokens) AS inputtokens, SUM(outputtokens) AS outputtokens,
            AVG(NULLIF(connectms,0)) AS connectavg, MAX(connectms) AS connectmax,
            AVG(NULLIF(firstaudioms,0)) AS audioavg
            FROM {' . self::TABLE . '} WHERE timecreated >= ?', [time(), $since]);
        $totals = [];
        foreach ((array)$summary as $key => $value) {
            $totals[$key] = (int)round((float)$value);
        }
        // At most 241 buckets, regardless of the number of sessions; no per-user rows are exported.
        $buckets = $DB->get_records_sql('SELECT FLOOR(connectms / 250) AS bucket, COUNT(*) AS total
            FROM {' . self::TABLE . '} WHERE timecreated >= ? AND connectms > 0
            GROUP BY FLOOR(connectms / 250) ORDER BY bucket', [$since]);
        $totals['connectp95'] = 0;
        $count = 0;
        foreach ($buckets as $bucket) {
            $count += (int)$bucket->total;
            if ($count >= (int)ceil($totals['connected'] * .95)) {
                $totals['connectp95'] = min(60000, ((int)$bucket->bucket + 1) * 250);
                break;
            }
        }
        $reasons = $DB->get_records_sql('SELECT reason, COUNT(*) AS total FROM {' . self::TABLE . '}
            WHERE timecreated >= ? AND ended = 1 GROUP BY reason ORDER BY reason', [$since]);
        $alerts = [];
        $active = self::active();
        if ($active >= self::limit()) {
            $alerts[] = 'capacity';
        }
        if ($totals['sessions'] >= 5 && $totals['failures'] / $totals['sessions'] >= .1) {
            $alerts[] = 'failures';
        }
        if ($totals['connected'] >= 5 && $totals['connectavg'] > 5000) {
            $alerts[] = 'latency';
        }
        if ($totals['sessions'] >= 5 && $totals['unclosed'] / $totals['sessions'] >= .1) {
            $alerts[] = 'unclosed';
        }
        if (ai\gemini::project_used_today() >= (int)ceil(ai\gemini::project_daily_limit() * .8)) {
            $alerts[] = 'quota';
        }
        return ['windowhours' => $hours, 'generated' => time(), 'active' => $active, 'limit' => self::limit(),
            'totals' => $totals, 'reasons' => array_map(static fn($row): int => (int)$row->total, $reasons),
            'alerts' => $alerts, 'usage_source' => 'client_estimate', 'retentiondays' => 30];
    }

    /** Retention uses creation time and does not depend on a client sending its final report. */
    public static function purge(): void {
        global $DB;
        $DB->delete_records_select(self::TABLE, 'timecreated < ?', [time() - 30 * DAYSECS]);
    }
}
