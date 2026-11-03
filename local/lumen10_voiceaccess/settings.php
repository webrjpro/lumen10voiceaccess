<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('users', new admin_externalpage('local_l10va_access',
    get_string('manageaccess', 'local_lumen10_voiceaccess'),
    new moodle_url('/local/lumen10_voiceaccess/manage.php'), 'local/lumen10_voiceaccess:manage'));
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_lumen10_voiceaccess', get_string('pluginname', 'local_lumen10_voiceaccess'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configcheckbox('local_lumen10_voiceaccess/enabled',
        get_string('enabled', 'local_lumen10_voiceaccess'), get_string('enabled_desc', 'local_lumen10_voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('local_lumen10_voiceaccess/allowai',
        get_string('allowai', 'local_lumen10_voiceaccess'), get_string('allowai_desc', 'local_lumen10_voiceaccess'), 0));
    $settings->add(new admin_setting_configtext('local_lumen10_voiceaccess/dailyquota',
        get_string('dailyquota', 'local_lumen10_voiceaccess'), '', 40, PARAM_INT));
    $settings->add(new admin_setting_configcheckbox('local_lumen10_voiceaccess/publiclogin',
        get_string('publiclogin', 'local_lumen10_voiceaccess'), get_string('publiclogin_desc', 'local_lumen10_voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('local_lumen10_voiceaccess/loginwithoutcaptcha',
        get_string('loginwithoutcaptcha', 'local_lumen10_voiceaccess'),
        get_string('loginwithoutcaptcha_desc', 'local_lumen10_voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('local_lumen10_voiceaccess/loginvoicegate',
        'Pergunta de acessibilidade por voz na página de login',
        'Ativa ou desativa a pergunta automática de acessibilidade por voz e entrada por frase cadastrada na tela de login.', 1));

    // Lumen10 Acesso Universal: institutional Google Gemini. The API key is read from the server
    // environment (LUMEN10_GEMINI_API_KEY) and is intentionally not an admin setting.
    $c = 'local_lumen10_voiceaccess';
    $settings->add(new admin_setting_heading($c . '/geminiheading', get_string('gemini_heading', $c),
        get_string('gemini_heading_desc', $c, (new moodle_url('/local/lumen10_voiceaccess/gemini.php'))->out())));
    $settings->add(new admin_setting_configcheckbox($c . '/gemini_enabled',
        get_string('gemini_enabled', $c), get_string('gemini_enabled_desc', $c), 0));
    $settings->add(new admin_setting_configcheckbox($c . '/gemini_live_enabled',
        get_string('liveenabled', $c), get_string('liveenabled_desc', $c), 0));
    $settings->add(new admin_setting_configselect($c . '/gemini_live_audience',
        get_string('liveaudience', $c), get_string('liveaudience_desc', $c), 'pilot', [
            'pilot' => get_string('gemini_audience_pilot', $c), 'all' => get_string('gemini_audience_all', $c)]));
    $settings->add(new admin_setting_configtextarea($c . '/gemini_live_pilotusers',
        get_string('livepilotusers', $c), get_string('livepilotusers_desc', $c), '', PARAM_TEXT, 40, 4));
    $settings->add(new admin_setting_configtext($c . '/gemini_live_seconds',
        get_string('liveduration', $c), get_string('liveduration_desc', $c), 180, PARAM_INT));
    $settings->add(new admin_setting_configtext($c . '/gemini_live_maxsessions',
        get_string('ops_capacity', $c), get_string('ops_capacity_desc', $c), 10, PARAM_INT));
    $settings->add(new admin_setting_configselect($c . '/gemini_audience',
        get_string('gemini_audience', $c), get_string('gemini_audience_desc', $c), 'pilot', [
            'pilot' => get_string('gemini_audience_pilot', $c), 'all' => get_string('gemini_audience_all', $c)]));
    $settings->add(new admin_setting_configtextarea($c . '/gemini_pilotusers',
        get_string('gemini_pilotusers', $c), get_string('gemini_pilotusers_desc', $c), '', PARAM_TEXT, 40, 4));
    $settings->add(new admin_setting_configselect($c . '/gemini_tier',
        get_string('gemini_tier', $c), get_string('gemini_tier_desc', $c), 'free', [
            'free' => get_string('gemini_tier_free', $c), 'paid' => get_string('gemini_tier_paid', $c)]));
    $settings->add(new admin_setting_configtext($c . '/gemini_textmodel',
        get_string('gemini_textmodel', $c), get_string('gemini_textmodel_desc', $c),
        \local_lumen10_voiceaccess\ai\gemini::DEFAULT_TEXT_MODEL, '/^[a-z0-9][a-z0-9.-]{1,99}$/'));
    $settings->add(new admin_setting_configtext($c . '/gemini_livemodel',
        get_string('gemini_livemodel', $c), get_string('gemini_livemodel_desc', $c),
        \local_lumen10_voiceaccess\ai\gemini::DEFAULT_LIVE_MODEL, '/^[a-z0-9][a-z0-9.-]{1,99}$/'));
    $voices = [];
    foreach (\local_lumen10_voiceaccess\ai\gemini::VOICES as $voice => $gender) {
        $voices[$voice] = $voice . ' (' . get_string('gemini_voice_' . $gender, $c) . ')';
    }
    $settings->add(new admin_setting_configselect($c . '/gemini_voice',
        get_string('gemini_voice', $c), get_string('gemini_voice_desc', $c),
        \local_lumen10_voiceaccess\ai\gemini::DEFAULT_VOICE, $voices));
    $settings->add(new admin_setting_configtext($c . '/gemini_projectdaily',
        get_string('gemini_projectdaily', $c), get_string('gemini_projectdaily_desc', $c),
        \local_lumen10_voiceaccess\ai\gemini::DEFAULT_PROJECT_DAILY, PARAM_INT));
    $ADMIN->add('localplugins', new admin_externalpage('local_l10va_gemini', get_string('gemini_status', $c),
        new moodle_url('/local/lumen10_voiceaccess/gemini.php'), 'moodle/site:config'));
}

