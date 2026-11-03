<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
// Lumen10 Acesso Universal: Gemini connection status, consumption and test (site administrators only).
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lumen10_voiceaccess\ai\gemini;

admin_externalpage_setup('local_l10va_gemini');
$c = 'local_lumen10_voiceaccess';
$PAGE->set_url(new moodle_url('/local/lumen10_voiceaccess/gemini.php'));
$health = \local_lumen10_voiceaccess\operations::snapshot();
if (optional_param('format', '', PARAM_ALPHA) === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($health);
    exit;
}

$test = null;
if (optional_param('test', 0, PARAM_BOOL) && confirm_sesskey() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    \core\session\manager::write_close();
    $test = gemini::test_connection();
}

$yes = get_string('yes');
$no = get_string('no');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gemini_status', $c));
echo html_writer::start_tag('section', ['id' => 'l10va-operations', 'aria-label' => get_string('ops_heading', $c)]);
echo $OUTPUT->heading(get_string('ops_heading', $c), 3);
echo html_writer::tag('p', get_string('ops_help', $c));
foreach ($health['alerts'] as $alert) {
    echo $OUTPUT->notification(get_string('ops_alert_' . $alert, $c), \core\output\notification::NOTIFY_WARNING);
}
$operational = new html_table();
$operational->attributes['class'] = 'generaltable';
$operational->head = [get_string('ops_metric', $c), get_string('ops_value', $c)];
$operational->data[] = [get_string('ops_active', $c), $health['active'] . ' / ' . $health['limit']];
foreach ($health['totals'] as $metric => $value) {
    $operational->data[] = [get_string('ops_' . $metric, $c), $value];
}
echo html_writer::table($operational);
if ($health['reasons']) {
    $reasons = new html_table();
    $reasons->head = [get_string('ops_reason', $c), get_string('ops_sessions', $c)];
    foreach ($health['reasons'] as $reason => $total) {
        $reasons->data[] = [get_string('ops_reason_' . \local_lumen10_voiceaccess\operations::reason($reason), $c), $total];
    }
    echo html_writer::table($reasons);
}
echo html_writer::link(new moodle_url($PAGE->url, ['format' => 'json']), get_string('ops_export', $c),
    ['class' => 'btn btn-secondary mb-3']);
echo html_writer::end_tag('section');

if (!gemini::configured()) {
    echo $OUTPUT->notification(get_string('gemini_nokey', $c, gemini::ENV), \core\output\notification::NOTIFY_WARNING);
}
if (gemini::tier() === 'free' && gemini::audience() === 'all') {
    echo $OUTPUT->notification(get_string('gemini_freewarning', $c), \core\output\notification::NOTIFY_WARNING);
}
if ($test !== null) {
    echo $OUTPUT->notification(get_string($test['ok'] ? 'gemini_test_ok' : 'gemini_test_fail', $c,
        (object)['ms' => $test['ms'], 'detail' => s($test['detail'])]),
        $test['ok'] ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

$used = gemini::project_used_today();
$limit = gemini::project_daily_limit();
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('gemini_provider', $c), 'Google Gemini API'],
    [get_string('gemini_key', $c), gemini::configured()
        ? s(gemini::masked_key()) . ' (' . get_string('gemini_keysource_' . gemini::key_source(), $c) . ')'
        : $no],
    [get_string('allowai', $c), get_config($c, 'allowai') ? $yes : $no],
    [get_string('gemini_enabled', $c), get_config($c, 'gemini_enabled') ? $yes : $no],
    [get_string('gemini_audience', $c), get_string('gemini_audience_' . gemini::audience(), $c) .
        (gemini::audience() === 'pilot' ? ' — ' . s(implode(', ', gemini::pilot_usernames()) ?: '—') : '')],
    [get_string('gemini_tier', $c), get_string('gemini_tier_' . gemini::tier(), $c)],
    [get_string('gemini_textmodel', $c), s(gemini::text_model())],
    [get_string('gemini_livemodel', $c), s(gemini::live_model())],
    [get_string('liveenabled', $c), get_config($c, 'gemini_live_enabled') ? $yes : $no],
    [get_string('liveduration', $c), \local_lumen10_voiceaccess\ai\live::duration()],
    [get_string('gemini_voice', $c), s(gemini::default_voice())],
    [get_string('dailyquota', $c), (int)get_config($c, 'dailyquota')],
    [get_string('gemini_today', $c), $used . ' / ' . $limit],
];
echo html_writer::table($table);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'test', 'value' => 1]);
echo html_writer::tag('button', get_string('gemini_test', $c), ['type' => 'submit', 'class' => 'btn btn-primary mb-3'] +
    (gemini::configured() ? [] : ['disabled' => 'disabled']));
echo html_writer::end_tag('form');
echo html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_lumen10_voiceaccess']),
    get_string('gemini_settingslink', $c), ['class' => 'btn btn-secondary mb-4']);

echo $OUTPUT->heading(get_string('gemini_usage', $c), 3);
$usage = new html_table();
$usage->attributes['class'] = 'generaltable';
$usage->head = [get_string('gemini_usage_day', $c), get_string('gemini_usage_requests', $c),
    get_string('gemini_usage_errors', $c), get_string('gemini_usage_tokens', $c), get_string('gemini_usage_users', $c)];
foreach (gemini::usage_summary(14) as $row) {
    $usage->data[] = [s($row->day), (int)$row->requests, (int)$row->errors,
        (int)$row->prompttokens + (int)$row->outputtokens, (int)$row->users];
}
if (empty($usage->data)) {
    echo html_writer::tag('p', get_string('gemini_usage_none', $c));
} else {
    echo html_writer::table($usage);
}
echo $OUTPUT->footer();
