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
 * lib.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

/** Serve course-introduction attachments through native course and activity permissions. */
function lumen10voiceaccess_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'intro') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/lumen10voiceaccess:view', $context);
    \mod_lumen10voiceaccess\access::require_use();
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    if ($filepath === '//') {
        $filepath = '/';
    }
    $file = get_file_storage()->get_file($context->id, 'mod_lumen10voiceaccess', 'intro', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

