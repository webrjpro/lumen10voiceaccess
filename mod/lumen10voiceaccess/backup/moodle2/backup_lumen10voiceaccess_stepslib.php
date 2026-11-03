<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
class backup_lumen10voiceaccess_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $node = new backup_nested_element('lumen10voiceaccess', ['id'],
            ['name', 'intro', 'introformat', 'timecreated', 'timemodified']);
        $node->set_source_table('lumen10voiceaccess', ['id' => backup::VAR_ACTIVITYID]);
        $node->annotate_files('mod_lumen10voiceaccess', 'intro', null);
        return $this->prepare_activity_structure($node);
    }
}

