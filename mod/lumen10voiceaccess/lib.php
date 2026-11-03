<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
function lumen10voiceaccess_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO, FEATURE_SHOW_DESCRIPTION, FEATURE_BACKUP_MOODLE2, FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GROUPS, FEATURE_GROUPINGS, FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}
function lumen10voiceaccess_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = $data->timemodified = time();
    return $DB->insert_record('lumen10voiceaccess', $data);
}
function lumen10voiceaccess_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('lumen10voiceaccess', $data);
}
function lumen10voiceaccess_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('lumen10voiceaccess', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('lumen10voiceaccess', ['id' => $id]);
    return true;
}

