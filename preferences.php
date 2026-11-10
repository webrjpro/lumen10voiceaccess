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
require_login();
\mod_lumen10voiceaccess\access::require_use();
$c = 'mod_lumen10voiceaccess';
$PAGE->set_url(new moodle_url('/mod/lumen10voiceaccess/preferences.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('l10va-preferences');
$PAGE->set_title(get_string('pluginname', $c));
$PAGE->set_heading(get_string('pluginname', $c));

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
$row = \mod_lumen10voiceaccess\preferences::get((int)$USER->id);
$form = new \mod_lumen10voiceaccess\form\preferences_form();
if ($data = $form->get_data()) {
    \mod_lumen10voiceaccess\preferences::save((int)$USER->id, (array)$data);
    redirect($PAGE->url, get_string('saved', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}
$initial = \mod_lumen10voiceaccess\preferences::public_settings($row);
$initial += ['provider' => $row->provider, 'model' => $row->model, 'consent' => $row->consent];
$form->set_data($initial);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($c . '/welcome', []);
echo $OUTPUT->render_from_template($c . '/readiness', [
    'aistatus' => get_string('aiready_' . \mod_lumen10voiceaccess\readiness::ai(
        \mod_lumen10voiceaccess\preferences::effective_ai($row)
    ), $c),
]);
if (!get_config($c, 'enabled')) {
    echo $OUTPUT->notification(get_string('disabled', $c), \core\output\notification::NOTIFY_INFO);
}
$used = $row->quotaday === gmdate('Y-m-d') ? (int)$row->quotaused : 0;
echo html_writer::tag('p', get_string('quota', $c) . ': ' . $used, ['class' => 'l10va-usage']);
$form->display();
echo $OUTPUT->footer();

