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
 * classes/readiness.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
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
