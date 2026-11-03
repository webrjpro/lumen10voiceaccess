<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
require_capability('local/lumen10_voiceaccess:manage', context_system::instance());
admin_externalpage_setup('local_l10va_access');
$c = 'local_lumen10_voiceaccess';
$PAGE->set_url(new moodle_url('/local/lumen10_voiceaccess/manage.php'));
$PAGE->set_heading(get_string('manageaccess', $c));
$PAGE->add_body_class('l10va-management');
$PAGE->requires->css(new moodle_url('/local/lumen10_voiceaccess/styles.css'));
$PAGE->requires->js_call_amd($c . '/voices', 'init', [[
    'automatic' => get_string('voiceauto', $c), 'sample' => get_string('voicesample', $c),
    'unavailable' => get_string('voiceunavailable', $c),
    'installed' => get_string('localinstalled', $c), 'localunsupported' => get_string('localunsupported', $c),
    'testing' => get_string('voicetesting', $c), 'done' => get_string('voicetestdone', $c),
    'failed' => get_string('voicetestfailed', $c),
]]);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$search = optional_param('search', '', PARAM_TEXT);
$selected = optional_param('userid', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$userpage = max(0, optional_param('userpage', 0, PARAM_INT));
$action = optional_param('action', '', PARAM_ALPHA);
$pairurl = null;
if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    require_sesskey();
    if ($action === 'delete') {
        if (!$DB->record_exists('local_lumen10_voiceaccess_access', ['userid' => $selected])) {
            redirect($PAGE->url, get_string('approvalmissing', $c), null, \core\output\notification::NOTIFY_INFO);
        }
        if (!optional_param('confirm', false, PARAM_BOOL)) {
            $person = $DB->get_record('user', ['id' => $selected], '*', MUST_EXIST);
            $continue = new single_button(new moodle_url('/local/lumen10_voiceaccess/manage.php', [
                'action' => 'delete', 'userid' => $selected, 'confirm' => 1,
            ]), get_string('delete'), 'post', \core\output\single_button::BUTTON_DANGER);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(get_string('deleteapprovalconfirm', $c, s(fullname($person))),
                $continue, $PAGE->url, ['confirmtitle' => get_string('deleteapproval', $c)]);
            echo $OUTPUT->footer();
            exit;
        }
        \local_lumen10_voiceaccess\access::delete_authorization($selected);
        redirect($PAGE->url, get_string('approvaldeleted', $c), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'revoke') {
        \local_lumen10_voiceaccess\access::revoke($selected);
    } else if ($action === 'devices') {
        \local_lumen10_voiceaccess\access::revoke_devices($selected);
    } else if ($action === 'pair') {
        $token = \local_lumen10_voiceaccess\access::issue_pair($selected);
        $pairurl = (new moodle_url('/local/lumen10_voiceaccess/bind.php'))->out(false) . '#' . $token;
    } else if ($action === 'setphrase') {
        $phrase = optional_param('voicephrase', '', PARAM_TEXT);
        \local_lumen10_voiceaccess\access::set_voice_phrase($selected, $phrase);
    } else if ($action === 'saveinst') {
        \local_lumen10_voiceaccess\preferences::save_institutional($_POST);
        redirect($PAGE->url, get_string('saved', $c), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        throw new moodle_exception('invalidrequest');
    }
    if ($pairurl === null) {
        redirect($PAGE->url, get_string('accesssaved', $c));
    }
}
$people = \local_lumen10_voiceaccess\people::search($search, $userpage, $selected);
$formurl = new moodle_url('/local/lumen10_voiceaccess/manage.php', ['search' => $search, 'userpage' => $people['page']]);
$form = new \local_lumen10_voiceaccess\form\access_form($formurl, ['users' => $people['options']]);
if ($data = $form->get_data()) {
    \local_lumen10_voiceaccess\access::grant((int)$data->userid, $data->declaration, !empty($data->confirmed));
    if (!empty($data->voicephrase)) {
        \local_lumen10_voiceaccess\access::set_voice_phrase((int)$data->userid, (string)$data->voicephrase);
    }
    redirect($PAGE->url, get_string('accesssaved', $c));
}
$instprefs = \local_lumen10_voiceaccess\preferences::defaults();
$instenabled = (bool)get_config($c, 'enabled');
$instlogingate = get_config($c, 'loginvoicegate') === false || (bool)get_config($c, 'loginvoicegate');
$installowai = (bool)get_config($c, 'allowai');
$instquota = (int)(get_config($c, 'dailyquota') !== false ? get_config($c, 'dailyquota') : 40);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageaccess', $c));
echo html_writer::tag('p', get_string('managehelp', $c));
if (has_capability('moodle/site:config', context_system::instance())) {
    echo html_writer::link(new moodle_url('/admin/settings.php', ['section' => $c]), get_string('institutionsettings', $c));
}
if ($pairurl !== null) {
    echo $OUTPUT->notification(get_string('pairhelp', $c), \core\output\notification::NOTIFY_INFO);
    echo html_writer::tag('p', html_writer::link($pairurl, get_string('pairlink', $c), ['rel' => 'noreferrer']));
    echo html_writer::label(get_string('pairlink', $c), 'l10va-pair-url');
    echo html_writer::empty_tag('input', ['id' => 'l10va-pair-url', 'type' => 'text', 'readonly' => 'readonly',
        'value' => $pairurl, 'class' => 'form-control', 'autocomplete' => 'off']);
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'my-4']);
echo html_writer::label(get_string('searchperson', $c), 'l10va-search');
echo html_writer::empty_tag('input', ['id' => 'l10va-search', 'name' => 'search', 'type' => 'search', 'value' => $search, 'class' => 'form-control']);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary mt-2']);
echo html_writer::end_tag('form');
if ($people['total'] === 0) {
    echo $OUTPUT->notification(get_string('nousersfound'), \core\output\notification::NOTIFY_INFO);
}
echo html_writer::tag('p', get_string('peopleavailable', $c,
    (object)['shown' => $people['shown'], 'total' => $people['total']]), ['class' => 'mt-3']);
echo $OUTPUT->paging_bar($people['total'], $people['page'], \local_lumen10_voiceaccess\people::PAGE_SIZE,
    new moodle_url('/local/lumen10_voiceaccess/manage.php', ['search' => $search]), 'userpage');
if ($selected) {
    $form->set_data(['userid' => $selected]);
}
$form->display();
echo $OUTPUT->heading(get_string('approvedlist', $c), 3);
$table = new html_table();
$table->head = [get_string('fullname'), get_string('declaration', $c), get_string('status'), get_string('actions')];
$total = $DB->count_records('local_lumen10_voiceaccess_access');
$records = $DB->get_records_sql('SELECT a.*,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,
    u.middlename,u.alternatename,u.deleted,u.suspended FROM {local_lumen10_voiceaccess_access} a JOIN {user} u ON u.id=a.userid
    ORDER BY a.active DESC,u.lastname,u.firstname,a.id', [], $page * 25, 25);
foreach ($records as $row) {
    $actions = '';
    foreach (['revoke' => 'revokeaccess', 'devices' => 'revokedevices', 'pair' => 'pairdevice'] as $key => $label) {
        if ($key === 'pair' && (empty($row->active) || $row->deleted || $row->suspended)) {
            continue;
        }
        $actions .= $OUTPUT->single_button(new moodle_url('/local/lumen10_voiceaccess/manage.php',
            ['action' => $key, 'userid' => $row->userid, 'sesskey' => sesskey()]), get_string($label, $c), 'post');
    }
    if (!empty($row->active) && !$row->deleted && !$row->suspended) {
        $actions .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'd-inline-flex align-items-center gap-1 mt-1',
        ]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'setphrase']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => (int)$row->userid]);
        $actions .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'voicephrase',
            'placeholder' => 'Nova frase (ex.: a vida é bela)',
            'class' => 'form-control form-control-sm',
            'style' => 'max-width:210px',
            'autocomplete' => 'off',
        ]);
        $actions .= html_writer::tag('button', 'Salvar frase', ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']);
        $actions .= html_writer::end_tag('form');
    }
    $declarationkey = ['blind' => 'declarationblind', 'severemobility' => 'declarationmobility', 'both' => 'declarationboth'][$row->declaration];
    $hasphrase = \local_lumen10_voiceaccess\access::has_voice_phrase((int)$row->userid);
    $statuslabel = get_string(!empty($row->active) && !$row->deleted && !$row->suspended ? 'approved' : 'revoked', $c) .
        ($hasphrase ? ' — Frase de voz ativa' : '');
    $deletebutton = new single_button(new moodle_url('/local/lumen10_voiceaccess/manage.php', [
        'action' => 'delete', 'userid' => $row->userid,
    ]), get_string('delete'), 'post', \core\output\single_button::BUTTON_DANGER);
    $deletebutton->class = 'singlebutton l10va-delete-approval';
    $deletebutton->set_attribute('aria-label', get_string('deleteapprovalfor', $c, fullname($row)));
    $namecell = html_writer::div(html_writer::span(s(fullname($row))) . $OUTPUT->render($deletebutton),
        'd-flex flex-wrap align-items-center justify-content-between gap-2');
    $table->data[] = [$namecell, s(get_string($declarationkey, $c)), s($statuslabel), $actions];
}
if ($total) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, 25, $PAGE->url);
} else {
    echo html_writer::tag('p', get_string('noapprovals', $c));
}

// Institutional accessibility preferences and read-only server AI status.
echo html_writer::start_tag('section', [
    'id' => 'l10va-inst-config',
    'class' => 'l10va-readiness mt-4',
    'aria-labelledby' => 'l10va-inst-heading',
]);
echo html_writer::tag('h3', get_string('institutionvoiceheading', $c), [
    'id' => 'l10va-inst-heading',
]);
echo html_writer::tag('p', 'As definições abaixo são configuradas pelo analista/instituição e aplicadas automaticamente a todas as pessoas autorizadas na lista acima. O microfone e o assistente de voz entram em ação automaticamente na conta da pessoa autorizada, sem botão flutuante na tela.');
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class' => 'l10va-inst-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveinst']);

echo html_writer::start_div('l10va-readiness-grid');

// Coluna 1: Robô de Voz, Microfone Automático e Síntese de Voz.
echo html_writer::start_tag('article');
echo html_writer::tag('h4', get_string('voicesettings', $c));
echo html_writer::tag('p', get_string('robotactive', $c), ['class' => 'l10va-readiness-state']);

echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'inst_enabled', 'name' => 'enabled', 'value' => '1',
    'class' => 'form-check-input'] + ($instenabled ? ['checked' => 'checked'] : []));
echo html_writer::label(get_string('enabled', $c), 'inst_enabled', true, ['class' => 'form-check-label ms-1']);
echo html_writer::end_div();

echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'inst_loginvoicegate', 'name' => 'loginvoicegate', 'value' => '1',
    'class' => 'form-check-input'] + ($instlogingate ? ['checked' => 'checked'] : []));
echo html_writer::label('Pergunta de acessibilidade por voz na página de login (Ativar / Desativar)', 'inst_loginvoicegate', true, ['class' => 'form-check-label ms-1']);
echo html_writer::end_div();

echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'inst_spoken', 'name' => 'spoken', 'value' => '1',
    'class' => 'form-check-input'] + (!empty($instprefs['spoken']) ? ['checked' => 'checked'] : []));
echo html_writer::label(get_string('speech', $c), 'inst_spoken', true, ['class' => 'form-check-label ms-1']);
echo html_writer::end_div();

echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'inst_resume', 'name' => 'resume', 'value' => '1',
    'class' => 'form-check-input'] + (!empty($instprefs['resume']) ? ['checked' => 'checked'] : []));
echo html_writer::label(get_string('resume', $c), 'inst_resume', true, ['class' => 'form-check-label ms-1']);
echo html_writer::end_div();

echo html_writer::label(get_string('recognitionmode', $c), 'id_recognitionmode', true, ['class' => 'd-block fw-bold mt-2']);
echo html_writer::select([
    'browser' => get_string('recognitionbrowser', $c),
    'device' => get_string('recognitiondevice', $c),
], 'recognitionmode', $instprefs['recognitionmode'] ?? 'browser', false, ['id' => 'id_recognitionmode', 'class' => 'form-control mb-2']);

echo html_writer::label(get_string('voice', $c), 'id_voiceuri', true, ['class' => 'd-block fw-bold mt-2']);
$voiceopts = ['' => get_string('voiceauto', $c)];
if (!empty($instprefs['voiceuri'])) {
    $voiceopts[$instprefs['voiceuri']] = $instprefs['voiceuri'];
}
echo html_writer::select($voiceopts, 'voiceuri', $instprefs['voiceuri'] ?? '', false, ['id' => 'id_voiceuri', 'class' => 'form-control mb-2']);

echo html_writer::label(get_string('rate', $c), 'id_rate', true, ['class' => 'd-block fw-bold mt-2']);
echo html_writer::select([
    '0.5' => '0.5×', '0.75' => '0.75×', '1' => '1×', '1.25' => '1.25×', '1.5' => '1.5×', '2' => '2×',
], 'rate', (string)($instprefs['rate'] ?? '1'), false, ['id' => 'id_rate', 'class' => 'form-control mb-2']);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'id' => 'id_pitch', 'name' => 'pitch', 'value' => (string)($instprefs['pitch'] ?? 1)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'id' => 'id_volume', 'name' => 'volume', 'value' => (string)($instprefs['volume'] ?? 1)]);
echo html_writer::tag('button', get_string('voicetest', $c), [
    'type' => 'button', 'id' => 'id_voicetest', 'class' => 'btn btn-secondary mt-2',
]);
echo html_writer::tag('span', '', ['id' => 'l10va-voice-test-status', 'role' => 'status', 'aria-live' => 'polite', 'class' => 'd-block mt-1']);
echo html_writer::end_tag('article');

// Gemini credentials and consumption settings are managed outside the analyst form.
echo html_writer::start_tag('article');
echo html_writer::tag('h4', get_string('gemini_heading', $c));
echo html_writer::tag('p', get_string('gemini_servermanaged', $c));
$geministatus = !\local_lumen10_voiceaccess\ai\gemini::configured() ? 'gemini_servermissing' :
    ($installowai && \local_lumen10_voiceaccess\ai\gemini::enabled() ? 'gemini_serverready' : 'gemini_serverdisabled');
echo html_writer::tag('p', get_string($geministatus, $c), ['class' => 'l10va-readiness-state']);
echo html_writer::tag('p', get_string('gemini_textmodel', $c) . ': ' . s(\local_lumen10_voiceaccess\ai\gemini::text_model()));
echo html_writer::tag('p', get_string('gemini_audience', $c) . ': ' .
    get_string('gemini_audience_' . \local_lumen10_voiceaccess\ai\gemini::audience(), $c));
echo html_writer::tag('p', get_string('dailyquota', $c) . ': ' . $instquota);
if (has_capability('moodle/site:config', context_system::instance())) {
    echo html_writer::link(new moodle_url('/local/lumen10_voiceaccess/gemini.php'),
        get_string('gemini_status', $c), ['class' => 'btn btn-secondary mt-2']);
    echo html_writer::link(new moodle_url('/admin/settings.php', ['section' => $c]),
        get_string('gemini_settingslink', $c), ['class' => 'btn btn-secondary mt-2']);
}
echo html_writer::end_tag('article');

echo html_writer::end_div();

echo html_writer::start_div('mt-3 d-flex flex-wrap gap-2 align-items-center');
echo html_writer::tag('button', get_string('save', $c), [
    'type' => 'submit', 'id' => 'l10va-inst-submit', 'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::start_tag('details', ['id' => 'l10va-bridge-info', 'class' => 'mt-3']);
echo html_writer::tag('summary', get_string('bridgeoptional', $c));
echo html_writer::tag('p', get_string('bridgedescription', $c), ['id' => 'l10va-bridge-help', 'class' => 'mt-2']);
echo html_writer::link(
    new moodle_url('/local/lumen10_voiceaccess/bridge.php'),
    get_string('bridgedownload', $c),
    ['class' => 'btn btn-secondary', 'id' => 'l10va-bridge-download', 'aria-describedby' => 'l10va-bridge-help']
);
echo html_writer::end_tag('details');
echo html_writer::end_tag('form');
echo html_writer::end_tag('section');

echo $OUTPUT->footer();
