<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unrecognized] = cli_get_params(['hours' => 24, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "VoiceAccess operational health, JSON only. --hours=1..720\nExit 0: no alert; 2: operational alert. No provider calls.\n";
    exit(0);
}
if ($unrecognized || !ctype_digit((string)$options['hours']) || (int)$options['hours'] < 1 || (int)$options['hours'] > 720) {
    cli_error('Use --hours=1..720');
}
$snapshot = \local_lumen10_voiceaccess\operations::snapshot((int)$options['hours']);
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($snapshot['alerts'] ? 2 : 0);
