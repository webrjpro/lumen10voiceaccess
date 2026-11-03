<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
defined('MOODLE_INTERNAL') || die();

class mod_lumen10voiceaccess_generator extends testing_module_generator {
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $record->name = $record->name ?? 'Universal access';
        return parent::create_instance($record, $options);
    }
}
