<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** Explains saved configuration without decrypting a key or contacting a provider. */
final class readiness {
    public static function ai(\stdClass $row): string {
        if (!get_config(preferences::COMPONENT, 'enabled') || !get_config(preferences::COMPONENT, 'allowai')) {
            return 'institution';
        }
        if (!in_array($row->provider, ['openai', 'anthropic', ai\gemini::PROVIDER], true)) {
            return 'provider';
        }
        if (empty($row->apikey)) {
            return 'key';
        }
        if (empty($row->consent)) {
            return 'consent';
        }
        if (!preg_match('/^[a-zA-Z0-9._:\/-]{1,100}$/D', $row->model)) {
            return 'model';
        }
        $limit = max(0, min(1000, (int)get_config(preferences::COMPONENT, 'dailyquota')));
        $used = $row->quotaday === gmdate('Y-m-d') ? (int)$row->quotaused : 0;
        if ($used >= $limit) {
            return 'quota';
        }
        return 'configured';
    }
}
