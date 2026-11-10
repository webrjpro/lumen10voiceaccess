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
 * learn.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_login();
\mod_lumen10voiceaccess\access::require_use();
if (!\mod_lumen10voiceaccess\learning_flow::enabled()) {
    redirect(new moodle_url('/my/'));
}
$c = 'mod_lumen10voiceaccess';
$view = optional_param('view', 'panel', PARAM_ALPHA);
$courseid = optional_param('id', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$pagesize = 5;
if (!in_array($view, ['panel', 'courses', 'course', 'help'], true)) {
    throw new moodle_exception('invalidrequest');
}
$courses = \mod_lumen10voiceaccess\learning_flow::courses();
if ($view === 'course') {
    if (!isset($courses[$courseid])) {
        throw new moodle_exception('flowcourseunavailable', $c);
    }
    $course = get_course($courseid);
    require_login($course);
    $PAGE->set_context(context_course::instance($courseid));
    $title = \mod_lumen10voiceaccess\learning_flow::course_name($course);
} else {
    $PAGE->set_context(context_user::instance($USER->id));
    $title = get_string('flow' . $view, $c);
}
$PAGE->set_url(\mod_lumen10voiceaccess\learning_flow::url($view, $view === 'course' ? $courseid : 0));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->add_body_class('l10va-flow-page');

$PAGE->requires->js_call_amd($c . '/learning_flow', 'init');
header('Cache-Control: private, no-store');
$data = ['title' => $title, 'panel' => $view === 'panel', 'coursesview' => $view === 'courses',
    'courseview' => $view === 'course', 'helpview' => $view === 'help',
    'coursesurl' => \mod_lumen10voiceaccess\learning_flow::url('courses')->out(false),
    'helpurl' => \mod_lumen10voiceaccess\learning_flow::url('help')->out(false),
    'settingsurl' => (new moodle_url('/mod/lumen10voiceaccess/preferences.php'))->out(false),
    'gradesurl' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
    'calendarurl' => (new moodle_url('/calendar/view.php'))->out(false),
    'messagesurl' => (new moodle_url('/message/index.php'))->out(false),
    'logouturl' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false)];
if (get_config('local_lumen10_biblioteca', 'version')) {
    $data['libraryurl'] = (new moodle_url('/local/lumen10_biblioteca/index.php'))->out(false);
}
if ($view === 'panel') {
    $tasks = \mod_lumen10voiceaccess\learning_flow::priorities();
    $data['deadlinewarning'] = \mod_lumen10voiceaccess\learning_flow::deadline_unavailable();
    $data['first'] = $tasks ? array_shift($tasks) : false;
    $data['following'] = $tasks;
    $data['hasfollowing'] = (bool)$tasks;
    $data['empty'] = !$data['first'];
    $data['nocourses'] = !$courses;
} else if ($view === 'courses') {
    $count = count($courses);
    $data['items'] = [];
    foreach (array_slice($courses, $page * $pagesize, $pagesize, true) as $course) {
        $data['items'][] = ['name' => \mod_lumen10voiceaccess\learning_flow::course_name($course),
            'url' => \mod_lumen10voiceaccess\learning_flow::url('course', $course->id)->out(false)];
    }
    $data['empty'] = !$data['items'];
} else if ($view === 'course') {
    $activities = \mod_lumen10voiceaccess\learning_flow::activities($course);
    $count = count($activities);
    $data['items'] = [];
    foreach (array_slice($activities, $page * $pagesize, $pagesize) as $activity) {
        $activity['kind'] = get_string('modulename', $activity['type']);
        $data['items'][] = $activity;
    }
    $data['empty'] = !$data['items'];
}
if ($view === 'courses' || $view === 'course') {
    if ($page > 0) {
        $previous = clone $PAGE->url;
        $previous->param('page', $page - 1);
        $data['previouspage'] = $previous->out(false);
    }
    if (($page + 1) * $pagesize < $count) {
        $next = clone $PAGE->url;
        $next->param('page', $page + 1);
        $data['nextpage'] = $next->out(false);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($c . '/learning_flow', $data);
echo $OUTPUT->footer();
