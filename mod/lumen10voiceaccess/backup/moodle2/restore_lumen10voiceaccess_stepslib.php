<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
class restore_lumen10voiceaccess_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        return $this->prepare_activity_structure([new restore_path_element('lumen10voiceaccess', '/activity/lumen10voiceaccess')]);
    }
    protected function process_lumen10voiceaccess($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $oldid = $data->id;
        $id = $DB->insert_record('lumen10voiceaccess', $data);
        $this->apply_activity_instance($id);
        $this->set_mapping('lumen10voiceaccess', $oldid, $id, true);
    }
    protected function after_execute() {
        $this->add_related_files('mod_lumen10voiceaccess', 'intro', null);
    }
}

