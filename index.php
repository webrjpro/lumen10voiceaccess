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
 * index.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
\mod_lumen10voiceaccess\access::require_use();
$context = context_course::instance($course->id);
$name = get_string('modulenameplural', 'lumen10voiceaccess');
$PAGE->set_url(new moodle_url('/mod/lumen10voiceaccess/index.php', ['id' => $id]));
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($name);
$PAGE->set_heading(format_string($course->fullname));

$table = new html_table();
$table->head = [get_string('name')];
$modinfo = get_fast_modinfo($course);
foreach (get_all_instances_in_course('lumen10voiceaccess', $course) as $activity) {
    $cm = $modinfo->get_cm($activity->coursemodule);
    if (!$cm->uservisible || !has_capability('mod/lumen10voiceaccess:view', $cm->context)) {
        continue;
    }
    $table->data[] = [html_writer::link(
        new moodle_url('/mod/lumen10voiceaccess/view.php', ['id' => $cm->id]),
        format_string($activity->name, true, ['context' => $cm->context]))];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($name);
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('noactivities', 'lumen10voiceaccess'), 'info');
}
echo $OUTPUT->footer();
