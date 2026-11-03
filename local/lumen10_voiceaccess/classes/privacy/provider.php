<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\request\{contextlist, approved_contextlist, userlist, approved_userlist, writer};

final class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $fields = [];
        foreach (['userid', 'preferences', 'provider', 'model', 'apikey', 'consent',
                'quotaused', 'quotaday', 'lastrequest', 'timemodified'] as $field) {
            $fields[$field] = 'privacy:metadata:' . $field;
        }
        $collection->add_database_table('local_lumen10_voiceaccess_user', $fields, 'privacy:metadata');
        $collection->add_database_table('local_lumen10_voiceaccess_access', [
            'userid' => 'privacy:metadata:userid', 'declaration' => 'privacy:declaration',
            'active' => 'privacy:approval', 'timecreated' => 'privacy:metadata:timemodified',
            'timemodified' => 'privacy:metadata:timemodified'], 'privacy:access');
        $collection->add_database_table('local_lumen10_voiceaccess_device', [
            'userid' => 'privacy:metadata:userid', 'tokenhash' => 'privacy:devicehash',
            'kind' => 'privacy:devicekind', 'expires' => 'privacy:deviceexpiry',
            'used' => 'privacy:deviceused', 'timecreated' => 'privacy:metadata:timemodified'], 'privacy:devices');
        $collection->add_database_table('local_lumen10_voiceaccess_aiusage', [
            'userid' => 'privacy:metadata:userid', 'day' => 'privacy:aiusage_day',
            'requests' => 'privacy:aiusage_requests', 'prompttokens' => 'privacy:aiusage_tokens',
            'outputtokens' => 'privacy:aiusage_tokens'], 'privacy:aiusage');
        $collection->add_database_table('local_lumen10_voiceaccess_live', [
            'userid' => 'privacy:metadata:userid', 'sessionhash' => 'privacy:ops_hash',
            'usageid' => 'privacy:ops', 'timecreated' => 'privacy:metadata:timemodified',
            'expires' => 'privacy:metadata:timemodified', 'lastseen' => 'privacy:metadata:timemodified',
            'ended' => 'privacy:ops', 'reason' => 'privacy:ops', 'connectms' => 'privacy:ops',
            'firstaudioms' => 'privacy:ops', 'inputtokens' => 'privacy:aiusage_tokens',
            'outputtokens' => 'privacy:aiusage_tokens', 'actions' => 'privacy:ops',
            'actionerrors' => 'privacy:ops', 'rejected' => 'privacy:ops'], 'privacy:ops');
        foreach (['openai', 'anthropic', 'gemini'] as $provider) {
            $collection->add_external_location_link($provider, ['command' => 'privacy:command'], 'privacy:external');
        }
        $collection->add_external_location_link('browser_speech', ['audio' => 'privacy:audio'], 'privacy:browser');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('local_lumen10_voiceaccess_user', ['userid' => $userid]) ||
                $DB->record_exists('local_lumen10_voiceaccess_access', ['userid' => $userid]) ||
                $DB->record_exists('local_lumen10_voiceaccess_device', ['userid' => $userid]) ||
                $DB->record_exists('local_lumen10_voiceaccess_aiusage', ['userid' => $userid]) ||
                $DB->record_exists('local_lumen10_voiceaccess_live', ['userid' => $userid])) {
            $list->add_user_context($userid);
        }
        return $list;
    }
    public static function export_user_data(approved_contextlist $list) {
        global $DB;
        $userid = (int)$list->get_user()->id;
        foreach ($list->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int)$context->instanceid === $userid) {
                $row = \local_lumen10_voiceaccess\preferences::get($userid);
                $row->haskey = !empty($row->apikey);
                unset($row->apikey, $row->id);
                writer::with_context($context)->export_data(['VoiceAccess'], $row);
                $approval = $DB->get_record('local_lumen10_voiceaccess_access', ['userid' => $userid]);
                if ($approval) {
                    unset($approval->id);
                    writer::with_context($context)->export_data(['VoiceAccess', 'Approval'], $approval);
                }
                $devices = array_values($DB->get_records('local_lumen10_voiceaccess_device', ['userid' => $userid],
                    'id', 'id,kind,expires,used,timecreated'));
                foreach ($devices as $device) { unset($device->id); }
                writer::with_context($context)->export_data(['VoiceAccess', 'Devices'], (object)['devices' => $devices]);
                $usage = array_values($DB->get_records('local_lumen10_voiceaccess_aiusage', ['userid' => $userid], 'day',
                    'id,day,provider,model,requests,errors,prompttokens,outputtokens'));
                foreach ($usage as $item) { unset($item->id); }
                writer::with_context($context)->export_data(['VoiceAccess', 'AI usage'], (object)['usage' => $usage]);
                $sessions = array_values($DB->get_records('local_lumen10_voiceaccess_live', ['userid' => $userid], 'timecreated',
                    'id,timecreated,expires,lastseen,ended,reason,connectms,firstaudioms,inputtokens,outputtokens,actions,actionerrors,rejected'));
                foreach ($sessions as $item) {unset($item->id);}
                writer::with_context($context)->export_data(['VoiceAccess', 'Operations'], (object)['sessions' => $sessions]);
            }
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_USER) {
            \local_lumen10_voiceaccess\access::erase_user_data((int)$context->instanceid);
        }
    }
    public static function delete_data_for_user(approved_contextlist $list) {
        $userid = (int)$list->get_user()->id;
        foreach ($list->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int)$context->instanceid === $userid) {
                self::delete_data_for_all_users_in_context($context);
            }
        }
    }
    public static function get_users_in_context(userlist $list) {
        $context = $list->get_context();
        if ($context->contextlevel === CONTEXT_USER) {
            $list->add_from_sql('userid', 'SELECT userid FROM {local_lumen10_voiceaccess_user} WHERE userid = :userid',
                ['userid' => $context->instanceid]);
            foreach (['local_lumen10_voiceaccess_access', 'local_lumen10_voiceaccess_device', 'local_lumen10_voiceaccess_aiusage', 'local_lumen10_voiceaccess_live'] as $table) {
                $list->add_from_sql('userid', 'SELECT userid FROM {' . $table . '} WHERE userid = :userid',
                    ['userid' => $context->instanceid]);
            }
        }
    }
    public static function delete_data_for_users(approved_userlist $list) {
        $context = $list->get_context();
        if ($context->contextlevel === CONTEXT_USER && in_array((int)$context->instanceid, array_map('intval', $list->get_userids()), true)) {
            self::delete_data_for_all_users_in_context($context);
        }
    }
}

