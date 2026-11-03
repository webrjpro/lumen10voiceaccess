<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

final class hook_listener {
    private static bool $learningpage = false;

    public static function learning_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $USER;
        self::$learningpage = false;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) ||
                during_initial_install() || !learning_flow::enabled() || !learning_flow::supports_page($PAGE)) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($_GET['sesskey'])) {
            $target = learning_flow::entry_target($PAGE->pagetype, (int)$PAGE->course->id);
            if ($target) {
                redirect($target);
            }
        }
        self::$learningpage = true;
        $PAGE->add_body_class('l10va-simple');
        $prefs = preferences::public_settings(preferences::get((int)$USER->id));
        if ($prefs['contrast']) {
            $PAGE->add_body_class('l10va-flow-contrast');
        }
        if ($prefs['large']) {
            $PAGE->add_body_class('l10va-flow-large');
        }
        $PAGE->requires->css(new \moodle_url('/local/lumen10_voiceaccess/styles.css'));
    }

    public static function learning_header(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (!self::$learningpage) {
            return;
        }
        $view = $PAGE->url->get_param('view') ?: 'panel';
        $hook->add_html($hook->renderer->render_from_template(preferences::COMPONENT . '/learning_nav', [
            'panelurl' => learning_flow::url()->out(false),
            'coursesurl' => learning_flow::url('courses')->out(false),
            'panel' => $PAGE->pagetype === 'local-lumen10_voiceaccess-learn' && $view === 'panel',
            'courses' => $PAGE->pagetype === 'local-lumen10_voiceaccess-learn' && in_array($view, ['courses', 'course'], true),
        ]));
    }

    public static function footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $USER, $CFG;
        $authenticated = isloggedin() && !isguestuser();
        // Public admissions are filled by the applicant or a trusted helper. Never start voice onboarding here.
        if ($PAGE->pagelayout === 'login' &&
                str_starts_with($PAGE->url->get_path(), '/mod/lumen10admissao/')) {
            return;
        }
        if (during_initial_install() ||
                !get_config(preferences::COMPONENT, 'version') ||
                in_array($PAGE->pagelayout, ['embedded', 'popup', 'maintenance', 'print'], true)) {
            return;
        }
        if ($authenticated && has_capability('local/lumen10_voiceaccess:manage', \context_system::instance())) {
            $PAGE->requires->js_call_amd(preferences::COMPONENT . '/management_navigation', 'init', [[
                'url' => (new \moodle_url('/local/lumen10_voiceaccess/manage.php'))->out(false),
                'title' => get_string('pluginname', preferences::COMPONENT),
                'icon' => (new \moodle_url('/local/lumen10_voiceaccess/pix/icon.svg'))->out(false),
            ]]);
        }
        $universal = (bool)get_config('mod_lumen10voiceaccess', 'version');
        $logingate = get_config(preferences::COMPONENT, 'loginvoicegate') === false ||
            (bool)get_config(preferences::COMPONENT, 'loginvoicegate');
        if (!$authenticated && get_config(preferences::COMPONENT, 'enabled') && $universal && $logingate) {
            if (!headers_sent()) {
                header('Permissions-Policy: microphone=(self), autoplay=(self), speaker-selection=(self), on-device-speech-recognition=(self)');
            }
            $PAGE->requires->js_call_amd(preferences::COMPONENT . '/login_gate', 'init', [[
                'endpoint' => (new \moodle_url('/local/lumen10_voiceaccess/voice_login.php'))->out(false),
                'root' => $CFG->wwwroot,
                'lang' => str_replace('_', '-', current_language()),
                'captchaExempt' => method_exists(login_policy::class, 'captcha_exempt') && login_policy::captcha_exempt(),
                'audio' => [
                    'welcome' => (new \moodle_url('/local/lumen10_voiceaccess/pix/login_welcome_short_20261031_sulafat.wav'))->out(false),
                    'whichphrase' => (new \moodle_url('/local/lumen10_voiceaccess/pix/login_whichphrase_sulafat.wav'))->out(false),
                    'confirmed' => (new \moodle_url('/local/lumen10_voiceaccess/pix/login_confirmed_sulafat.wav'))->out(false),
                    'cancelled' => (new \moodle_url('/local/lumen10_voiceaccess/pix/login_cancelled_sulafat.wav'))->out(false),
                    'invalid' => (new \moodle_url('/local/lumen10_voiceaccess/pix/login_invalid_sulafat.wav'))->out(false),
                ],
            ]]);
            return;
        }
        $publiclogin = !$authenticated && $PAGE->pagetype === 'login-index' && access::public_allowed();
        $isapproved = $authenticated && access::approved((int)$USER->id);
        $configpage = in_array($PAGE->pagetype, [
            'local-lumen10_voiceaccess-index',
            'local-lumen10_voiceaccess-manage',
            'mod-lumen10voiceaccess-view',
        ], true) || (bool)preg_match('~/(?:local/lumen10_voiceaccess/(?:index|manage)|mod/lumen10voiceaccess/view)\.php$~',
            $PAGE->url->get_path());
        $analystmanage = $authenticated && $configpage &&
            has_capability('local/lumen10_voiceaccess:manage', \context_system::instance());
        if (!get_config(preferences::COMPONENT, 'enabled') ||
                ($authenticated ? (!$isapproved && !$analystmanage) : !$publiclogin)) {
            return;
        }
        if (!headers_sent()) {
            header('Permissions-Policy: microphone=(self), autoplay=(self), speaker-selection=(self), on-device-speech-recognition=(self)');
        }
        if ($isapproved || $publiclogin) {
            $PAGE->requires->js_call_amd(preferences::COMPONENT . '/access_watch', 'init', [[
                'url' => (new \moodle_url('/local/lumen10_voiceaccess/status.php'))->out(false),
                'owner' => $authenticated ? (string)$USER->id : 'login',
            ]]);
        }
        $row = $authenticated ? preferences::get((int)$USER->id) : null;
        $ai = $authenticated && $row ? preferences::effective_ai($row) : null;
        $prefs = $row ? preferences::public_settings($row) : preferences::defaults();
        if (!$authenticated) {
            $prefs['spoken'] = true;
        }
        $strings = get_string_manager()->load_component_strings(preferences::COMPONENT, current_language());
        $navigation = ['courses' => [], 'activities' => [], 'currentcm' => 0];
        if ($authenticated) {
            try {
                $navigation = self::$learningpage ? learning_flow::navigation($PAGE) :
                    navigation::snapshot((int)$USER->id, $PAGE->course, $PAGE->cm->id ?? 0);
            } catch (\Throwable $e) {
                // An unavailable integration must not break Moodle or the local command engine.
                error_log('VoiceAccess navigation snapshot unavailable: ' . get_class($e));
            }
        }
        $controllerconfig = [
            'root' => $CFG->wwwroot, 'endpoint' => (new \moodle_url('/local/lumen10_voiceaccess/interpret.php'))->out(false),
            'sesskey' => sesskey(), 'lang' => str_replace('_', '-', current_language()),
            'prefs' => $prefs, 'strings' => $strings, 'login' => (bool)$publiclogin, 'navigation' => $navigation,
            'owner' => $authenticated ? (string)$USER->id : 'login',
            'configpage' => $configpage,
            'autostart' => $isapproved,
            'ai' => $authenticated && (bool)get_config(preferences::COMPONENT, 'allowai') && !empty($ai->consent) &&
                !empty($ai->apikey) && $ai->provider !== 'none',
            'live' => [
                'conversation' => hash_hmac('sha256', sesskey(), (string)($CFG->passwordsaltmain ?? 'l10va-session')),
                'enabled' => $authenticated && ai\live::enabled((int)$USER->id),
                'endpoint' => (new \moodle_url('/local/lumen10_voiceaccess/live.php'))->out(false),
                'socket' => ai\live::SOCKET,
                'worklet' => (new \moodle_url('/local/lumen10_voiceaccess/audio_worklet.js'))->out(false),
                'voice' => ai\gemini::default_voice(),
                'voices' => array_keys(ai\gemini::VOICES),
                'intents' => ai\live::intents(),
            ],
            'audio' => [
                'done' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_done_sulafat.wav'))->out(false),
                'unknown' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_unknown_sulafat.wav'))->out(false),
                'notfound' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_notfound_sulafat.wav'))->out(false),
                'activated' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_activated_sulafat.wav'))->out(false),
                'repeat' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_repeat_sulafat.wav'))->out(false),
                'aifailed' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_aifailed_sulafat.wav'))->out(false),
                'voicesample' => (new \moodle_url('/local/lumen10_voiceaccess/pix/va_voicesample_sulafat.wav'))->out(false),
            ],
        ];
        // Keep the growing vocabulary and navigation outside AMD argument-size limits.
        $PAGE->requires->js_call_amd(preferences::COMPONENT . '/controller', 'init_from_dom', ['l10va-controller-config']);
        $hook->add_html(\html_writer::tag('script', json_encode($controllerconfig,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE),
            ['type' => 'application/json', 'id' => 'l10va-controller-config']));
        $hook->add_html($hook->renderer->render_from_template(preferences::COMPONENT . '/overlay', [
            'settingsurl' => (new \moodle_url('/local/lumen10_voiceaccess/index.php'))->out(false),
        ]));
    }
}

