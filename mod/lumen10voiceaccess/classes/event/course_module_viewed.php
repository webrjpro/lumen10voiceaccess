<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace mod_lumen10voiceaccess\event;
defined('MOODLE_INTERNAL') || die();
class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'lumen10voiceaccess';
    }
    public static function get_objectid_mapping() {
        return ['db' => 'lumen10voiceaccess', 'restore' => 'lumen10voiceaccess'];
    }
}

