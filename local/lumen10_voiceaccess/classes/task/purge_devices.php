<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess\task;
defined('MOODLE_INTERNAL') || die();
final class purge_devices extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('purgeexpireddevices', 'local_lumen10_voiceaccess');
    }
    public function execute(): void {
        global $DB;
        \local_lumen10_voiceaccess\operations::purge();
        $DB->delete_records_select('local_l10va_device', 'expires <= :now OR (kind=:kind AND used=1)',
            ['now' => time(), 'kind' => 'pair']);
    }
}
