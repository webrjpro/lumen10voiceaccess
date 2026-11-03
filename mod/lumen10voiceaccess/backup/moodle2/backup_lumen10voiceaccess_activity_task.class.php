<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/backup_lumen10voiceaccess_stepslib.php');
class backup_lumen10voiceaccess_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new backup_lumen10voiceaccess_activity_structure_step('voiceaccess_structure', 'voiceaccess.xml'));
    }
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        return preg_replace('/' . $base . '\/mod\/lumen10voiceaccess\/view.php\?id=([0-9]+)/',
            '$@LUMEN10VOICEACCESSVIEWBYID*$1@$', $content);
    }
}

