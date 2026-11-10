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
 * view.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('lumen10voiceaccess', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/lumen10voiceaccess:view', $context);
\mod_lumen10voiceaccess\access::require_use();
$instance = $DB->get_record('lumen10voiceaccess', ['id' => $cm->instance], '*', MUST_EXIST);
$PAGE->set_url(new moodle_url('/mod/lumen10voiceaccess/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

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
echo $OUTPUT->render_from_template('mod_lumen10voiceaccess/welcome', []);
echo $OUTPUT->single_button(new moodle_url('/mod/lumen10voiceaccess/preferences.php'),
    get_string('settings', 'mod_lumen10voiceaccess'), 'get');
echo $OUTPUT->footer();

