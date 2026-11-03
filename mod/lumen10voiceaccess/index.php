<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
\local_lumen10_voiceaccess\access::require_use();
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
