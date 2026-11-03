<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** Registered accounts available to an authorised analyst, with explicit pagination. */
final class people {
    public const PAGE_SIZE = 50;

    public static function search(string $search = '', int $page = 0, int $selected = 0): array {
        global $DB, $CFG;
        require_capability('local/lumen10_voiceaccess:manage', \context_system::instance());
        $where = 'deleted=0 AND suspended=0 AND id<>:guest';
        $params = ['guest' => (int)$CFG->siteguest];
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $where .= ' AND (' . $DB->sql_like($DB->sql_fullname(), ':fullname', false) . ' OR ' .
                $DB->sql_like('firstname', ':first', false) . ' OR ' . $DB->sql_like('lastname', ':last', false) .
                ' OR ' . $DB->sql_like('email', ':email', false) . ' OR ' . $DB->sql_like('username', ':username', false) . ')';
            $params += array_fill_keys(['fullname', 'first', 'last', 'email', 'username'], $like);
        }
        $total = $DB->count_records_select('user', $where, $params);
        $page = min(max(0, $page), max(0, (int)ceil($total / self::PAGE_SIZE) - 1));
        $fields = 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email';
        $records = $DB->get_records_select('user', $where, $params, 'firstname,lastname,id', $fields,
            $page * self::PAGE_SIZE, self::PAGE_SIZE);
        // A submitted selection must survive pagination, filters and validation errors.
        if ($selected > 0 && !isset($records[$selected])) {
            $person = $DB->get_record_select('user', 'id=:id AND deleted=0 AND suspended=0 AND id<>:guest',
                ['id' => $selected, 'guest' => (int)$CFG->siteguest], $fields);
            if ($person) {
                $records = [$person->id => $person] + $records;
            }
        }
        $options = [0 => get_string('choose')];
        foreach ($records as $user) {
            $options[$user->id] = fullname($user) . ' (' . $user->email . ')';
        }
        return ['options' => $options, 'total' => $total, 'page' => $page, 'shown' => min(self::PAGE_SIZE, $total - $page * self::PAGE_SIZE)];
    }
}
