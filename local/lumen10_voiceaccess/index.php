<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
require_login();
\local_lumen10_voiceaccess\access::require_use();
$c = 'local_lumen10_voiceaccess';
$PAGE->set_url(new moodle_url('/local/lumen10_voiceaccess/index.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('l10va-preferences');
$PAGE->set_title(get_string('pluginname', $c));
$PAGE->set_heading(get_string('pluginname', $c));
$PAGE->requires->css(new moodle_url('/local/lumen10_voiceaccess/styles.css'));
$PAGE->requires->js_call_amd($c . '/voices', 'init', [[
    'automatic' => get_string('voiceauto', $c), 'sample' => get_string('voicesample', $c),
    'unavailable' => get_string('voiceunavailable', $c),
    'installed' => get_string('localinstalled', $c), 'localunsupported' => get_string('localunsupported', $c),
    'testing' => get_string('voicetesting', $c), 'done' => get_string('voicetestdone', $c),
    'failed' => get_string('voicetestfailed', $c),
]]);
$devicetext = [];
foreach (['deviceinsecure', 'devicenospeech', 'devicespeechready', 'devicelocalunsupported', 'devicelocalinstall',
        'devicelocalready', 'devicemicgranted', 'devicemicdenied', 'devicemicprompt', 'devicemicunknown',
        'devicevoicesready', 'devicevoicespending', 'devicenovoices', 'devicetimeout'] as $key) {
    $devicetext[$key] = get_string($key, $c);
}
$PAGE->requires->js_call_amd($c . '/device_check', 'init', [$devicetext]);
$row = \local_lumen10_voiceaccess\preferences::get((int)$USER->id);
$form = new \local_lumen10_voiceaccess\form\preferences_form();
if ($data = $form->get_data()) {
    \local_lumen10_voiceaccess\preferences::save((int)$USER->id, (array)$data);
    redirect($PAGE->url, get_string('saved', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}
$initial = \local_lumen10_voiceaccess\preferences::public_settings($row);
$initial += ['provider' => $row->provider, 'model' => $row->model, 'consent' => $row->consent];
$form->set_data($initial);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($c . '/welcome', []);
echo $OUTPUT->render_from_template($c . '/readiness', [
    'aistatus' => get_string('aiready_' . \local_lumen10_voiceaccess\readiness::ai(
        \local_lumen10_voiceaccess\preferences::effective_ai($row)
    ), $c),
]);
if (!get_config($c, 'enabled')) {
    echo $OUTPUT->notification(get_string('disabled', $c), \core\output\notification::NOTIFY_INFO);
}
$used = $row->quotaday === gmdate('Y-m-d') ? (int)$row->quotaused : 0;
echo html_writer::tag('p', get_string('quota', $c) . ': ' . $used, ['class' => 'l10va-usage']);
$form->display();
echo $OUTPUT->footer();

