<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
$callbacks = [[
    'hook' => \core\hook\output\before_footer_html_generation::class,
    'callback' => [\local_lumen10_voiceaccess\hook_listener::class, 'footer'],
], [
    'hook' => \core\hook\after_config::class,
    'callback' => [\local_lumen10_voiceaccess\login_policy::class, 'configure'],
], [
    'hook' => \core\hook\output\before_http_headers::class,
    'callback' => [\local_lumen10_voiceaccess\hook_listener::class, 'learning_headers'],
], [
    'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
    'callback' => [\local_lumen10_voiceaccess\hook_listener::class, 'learning_header'],
]];

