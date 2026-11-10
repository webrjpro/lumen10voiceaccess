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
 * settings.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('users', new admin_externalpage('lumen10voiceaccess_access',
    get_string('manageaccess', 'mod_lumen10voiceaccess'),
    new moodle_url('/mod/lumen10voiceaccess/manage.php'), 'mod/lumen10voiceaccess:manage'));
if ($hassiteconfig) {
    $settings->add(new admin_setting_configcheckbox('mod_lumen10voiceaccess/enabled',
        get_string('enabled', 'mod_lumen10voiceaccess'), get_string('enabled_desc', 'mod_lumen10voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('mod_lumen10voiceaccess/allowai',
        get_string('allowai', 'mod_lumen10voiceaccess'), get_string('allowai_desc', 'mod_lumen10voiceaccess'), 0));
    $settings->add(new admin_setting_configtext('mod_lumen10voiceaccess/dailyquota',
        get_string('dailyquota', 'mod_lumen10voiceaccess'), '', 40, PARAM_INT));
    $settings->add(new admin_setting_configcheckbox('mod_lumen10voiceaccess/publiclogin',
        get_string('publiclogin', 'mod_lumen10voiceaccess'), get_string('publiclogin_desc', 'mod_lumen10voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('mod_lumen10voiceaccess/loginwithoutcaptcha',
        get_string('loginwithoutcaptcha', 'mod_lumen10voiceaccess'),
        get_string('loginwithoutcaptcha_desc', 'mod_lumen10voiceaccess'), 1));
    $settings->add(new admin_setting_configcheckbox('mod_lumen10voiceaccess/loginvoicegate',
        get_string('loginvoicegate', 'mod_lumen10voiceaccess'),
        get_string('loginvoicegate_desc', 'mod_lumen10voiceaccess'), 1));

    // Lumen10 Acesso Universal: institutional Google Gemini. The API key is read from the server
    // environment (LUMEN10_GEMINI_API_KEY) and is intentionally not an admin setting.
    $c = 'mod_lumen10voiceaccess';
    $settings->add(new admin_setting_heading($c . '/geminiheading', get_string('gemini_heading', $c),
        get_string('gemini_heading_desc', $c, (new moodle_url('/mod/lumen10voiceaccess/gemini.php'))->out())));
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
        \mod_lumen10voiceaccess\ai\gemini::DEFAULT_TEXT_MODEL, '/^[a-z0-9][a-z0-9.-]{1,99}$/'));
    $settings->add(new admin_setting_configtext($c . '/gemini_livemodel',
        get_string('gemini_livemodel', $c), get_string('gemini_livemodel_desc', $c),
        \mod_lumen10voiceaccess\ai\gemini::DEFAULT_LIVE_MODEL, '/^[a-z0-9][a-z0-9.-]{1,99}$/'));
    $voices = [];
    foreach (\mod_lumen10voiceaccess\ai\gemini::VOICES as $voice => $gender) {
        $voices[$voice] = $voice . ' (' . get_string('gemini_voice_' . $gender, $c) . ')';
    }
    $settings->add(new admin_setting_configselect($c . '/gemini_voice',
        get_string('gemini_voice', $c), get_string('gemini_voice_desc', $c),
        \mod_lumen10voiceaccess\ai\gemini::DEFAULT_VOICE, $voices));
    $settings->add(new admin_setting_configtext($c . '/gemini_projectdaily',
        get_string('gemini_projectdaily', $c), get_string('gemini_projectdaily_desc', $c),
        \mod_lumen10voiceaccess\ai\gemini::DEFAULT_PROJECT_DAILY, PARAM_INT));
    $ADMIN->add('modsettings', new admin_externalpage('lumen10voiceaccess_gemini', get_string('gemini_status', $c),
        new moodle_url('/mod/lumen10voiceaccess/gemini.php'), 'moodle/site:config'));
}

