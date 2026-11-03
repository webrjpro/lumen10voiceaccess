<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/restore_lumen10voiceaccess_stepslib.php');
class restore_lumen10voiceaccess_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_lumen10voiceaccess_activity_structure_step('voiceaccess_structure', 'voiceaccess.xml'));
    }
    public static function define_decode_contents() {
        return [new restore_decode_content('lumen10voiceaccess', ['intro'], 'lumen10voiceaccess')];
    }
    public static function define_decode_rules() {
        return [new restore_decode_rule('LUMEN10VOICEACCESSVIEWBYID', '/mod/lumen10voiceaccess/view.php?id=$1', 'course_module')];
    }
    public static function define_restore_log_rules() { return []; }
}

