<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess\event;
defined('MOODLE_INTERNAL') || die();
final class access_updated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    public static function get_name(): string {
        return get_string('accessupdated', 'local_lumen10_voiceaccess');
    }
    public function get_description(): string {
        return 'User ' . $this->userid . ' updated VoiceAccess approval for user ' . $this->relateduserid . '.';
    }
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/lumen10_voiceaccess/manage.php');
    }
}
