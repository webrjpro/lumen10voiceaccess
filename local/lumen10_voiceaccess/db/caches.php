<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();
$definitions = [
    // No Google credential, audio or transcript is ever cached.
    'live_sessions' => ['mode' => cache_store::MODE_APPLICATION, 'ttl' => 360],
];
