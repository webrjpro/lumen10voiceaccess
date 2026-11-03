<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('lumen10voiceaccess', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/lumen10voiceaccess:view', $context);
\local_lumen10_voiceaccess\access::require_use();
$instance = $DB->get_record('lumen10voiceaccess', ['id' => $cm->instance], '*', MUST_EXIST);
$PAGE->set_url(new moodle_url('/mod/lumen10voiceaccess/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/lumen10_voiceaccess/styles.css'));
$event = \mod_lumen10voiceaccess\event\course_module_viewed::create(['objectid' => $instance->id, 'context' => $context]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('lumen10voiceaccess', $instance);
$event->trigger();
(new completion_info($course))->set_module_viewed($cm);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));
if ($instance->intro) {
    echo $OUTPUT->box(format_module_intro('lumen10voiceaccess', $instance, $cm->id), 'generalbox mod_introbox');
}
echo $OUTPUT->render_from_template('local_lumen10_voiceaccess/welcome', []);
echo $OUTPUT->single_button(new moodle_url('/local/lumen10_voiceaccess/index.php'),
    get_string('settings', 'local_lumen10_voiceaccess'), 'get');
echo $OUTPUT->footer();

