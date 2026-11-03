<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
require_login();
\local_lumen10_voiceaccess\access::require_use();
if (!\local_lumen10_voiceaccess\learning_flow::enabled()) {
    redirect(new moodle_url('/my/'));
}
$c = 'local_lumen10_voiceaccess';
$view = optional_param('view', 'panel', PARAM_ALPHA);
$courseid = optional_param('id', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$pagesize = 5;
if (!in_array($view, ['panel', 'courses', 'course', 'help'], true)) {
    throw new moodle_exception('invalidrequest');
}
$courses = \local_lumen10_voiceaccess\learning_flow::courses();
if ($view === 'course') {
    if (!isset($courses[$courseid])) {
        throw new moodle_exception('flowcourseunavailable', $c);
    }
    $course = get_course($courseid);
    require_login($course);
    $PAGE->set_context(context_course::instance($courseid));
    $title = \local_lumen10_voiceaccess\learning_flow::course_name($course);
} else {
    $PAGE->set_context(context_user::instance($USER->id));
    $title = get_string('flow' . $view, $c);
}
$PAGE->set_url(\local_lumen10_voiceaccess\learning_flow::url($view, $view === 'course' ? $courseid : 0));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->add_body_class('l10va-flow-page');
$PAGE->requires->css(new moodle_url('/local/lumen10_voiceaccess/styles.css'));
$PAGE->requires->js_call_amd($c . '/learning_flow', 'init');
header('Cache-Control: private, no-store');
$data = ['title' => $title, 'panel' => $view === 'panel', 'coursesview' => $view === 'courses',
    'courseview' => $view === 'course', 'helpview' => $view === 'help',
    'coursesurl' => \local_lumen10_voiceaccess\learning_flow::url('courses')->out(false),
    'helpurl' => \local_lumen10_voiceaccess\learning_flow::url('help')->out(false),
    'settingsurl' => (new moodle_url('/local/lumen10_voiceaccess/index.php'))->out(false),
    'gradesurl' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
    'calendarurl' => (new moodle_url('/calendar/view.php'))->out(false),
    'messagesurl' => (new moodle_url('/message/index.php'))->out(false),
    'logouturl' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false)];
if (get_config('local_lumen10_biblioteca', 'version')) {
    $data['libraryurl'] = (new moodle_url('/local/lumen10_biblioteca/index.php'))->out(false);
}
if ($view === 'panel') {
    $tasks = \local_lumen10_voiceaccess\learning_flow::priorities();
    $data['deadlinewarning'] = \local_lumen10_voiceaccess\learning_flow::deadline_unavailable();
    $data['first'] = $tasks ? array_shift($tasks) : false;
    $data['following'] = $tasks;
    $data['hasfollowing'] = (bool)$tasks;
    $data['empty'] = !$data['first'];
    $data['nocourses'] = !$courses;
} else if ($view === 'courses') {
    $count = count($courses);
    $data['items'] = [];
    foreach (array_slice($courses, $page * $pagesize, $pagesize, true) as $course) {
        $data['items'][] = ['name' => \local_lumen10_voiceaccess\learning_flow::course_name($course),
            'url' => \local_lumen10_voiceaccess\learning_flow::url('course', $course->id)->out(false)];
    }
    $data['empty'] = !$data['items'];
} else if ($view === 'course') {
    $activities = \local_lumen10_voiceaccess\learning_flow::activities($course);
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
