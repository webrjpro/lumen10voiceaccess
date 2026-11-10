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
 * tests/operations_test.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
defined('MOODLE_INTERNAL') || die();

use mod_lumen10voiceaccess\ai\{gemini, live};

/** Operational persistence, admission, privacy and recovery without provider traffic. */
final class operations_test extends \advanced_testcase {
    private function user(): \stdClass {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/filelib.php');
        $CFG->local_lumen10_voiceaccess_geminikey = 'synthetic-operational-key';
        foreach (['enabled'=>1, 'allowai'=>1, 'gemini_enabled'=>1, 'gemini_live_enabled'=>1,
                'dailyquota'=>40, 'gemini_projectdaily'=>100, 'gemini_audience'=>'all',
                'gemini_live_audience'=>'all'] as $key=>$value) {
            set_config($key, $value, preferences::COMPONENT);
        }
        $user = $this->getDataGenerator()->create_user();
        access::grant((int)$user->id, 'blind', true);
        return $user;
    }

    private function start(int $userid): array {
        \curl::mock_response('{"name":"auth_tokens/synthetic-operational"}');
        return live::start($userid, 'Sulafat', true);
    }

    public function test_capacity_rejection_does_not_consume_user_or_project_quota(): void {
        $first=$this->user();$second=$this->user();
        set_config('gemini_live_maxsessions', 1, preferences::COMPONENT);
        $session=$this->start((int)$first->id);
        $before=gemini::project_used_today();
        try {
            live::start((int)$second->id, 'Sulafat', true);
            $this->fail('Capacity exceeded');
        } catch (\moodle_exception $e) {
            $this->assertSame('livecapacity', $e->errorcode);
        }
        $this->assertEquals(0, preferences::get((int)$second->id)->quotaused);
        $this->assertSame($before, gemini::project_used_today());
        live::report((int)$first->id, $session['session'], 0, 0, true);
        $this->assertSame(0, operations::active());
        $this->assertNotEmpty($this->start((int)$second->id)['session']);
    }

    public function test_reports_survive_cache_loss_and_never_duplicate_usage(): void {
        global $DB;
        $u=$this->user();$s=$this->start((int)$u->id);
        $this->assertTrue(live::report((int)$u->id, $s['session'], 30, 12, false,
            ['connectms'=>600, 'firstaudioms'=>200, 'actions'=>2, 'actionerrors'=>1]));
        \cache::make(preferences::COMPONENT, 'live_sessions')->purge();
        $this->assertTrue(live::report((int)$u->id, $s['session'], 30, 12, false));
        $usage=$DB->get_record(gemini::TABLE, ['userid'=>$u->id]);
        $this->assertEquals(30, $usage->prompttokens);$this->assertEquals(12, $usage->outputtokens);
        $this->assertSame(1, operations::active());
        $this->assertFalse(live::report((int)$u->id, $s['session'], 35, 15, true, ['reason'=>'navigation']));
        $this->assertFalse(live::report((int)$u->id, $s['session'], 999, 999, false));
        $this->assertEquals(35, $DB->get_field(gemini::TABLE, 'prompttokens', ['userid'=>$u->id]));
        $this->assertSame(['navigation'=>1], operations::snapshot()['reasons']);
    }

    public function test_foreign_session_cannot_pollute_metrics_or_release_capacity(): void {
        global $DB;
        $u=$this->user();$other=$this->user();$s=$this->start((int)$u->id);
        $this->assertFalse(live::report((int)$other->id, $s['session'], 99, 99, true,
            ['connectms'=>999, 'reason'=>'offline', 'actions'=>100]));
        $row=$DB->get_record(operations::TABLE, ['userid'=>$u->id]);
        $this->assertEquals(0, $row->ended);$this->assertEquals(0, $row->actions);
        $this->assertEquals(0, $row->connectms);$this->assertSame(1, operations::active());
    }

    public function test_untrusted_metrics_are_bounded_and_errors_cannot_exceed_actions(): void {
        global $DB;
        $u=$this->user();$s=$this->start((int)$u->id);
        live::report((int)$u->id, $s['session'], PHP_INT_MAX, -9, true,
            ['connectms'=>PHP_INT_MAX,'firstaudioms'=>-4,'actions'=>2,'actionerrors'=>500,
                'rejected'=>999999,'reason'=>'secret-token-or-injected-page']);
        $row=$DB->get_record(operations::TABLE, ['userid'=>$u->id]);
        $this->assertEquals(1000000,$row->inputtokens);$this->assertEquals(0,$row->outputtokens);
        $this->assertEquals(60000,$row->connectms);$this->assertEquals(0,$row->firstaudioms);
        $this->assertEquals(2,$row->actionerrors);$this->assertEquals(200,$row->rejected);
        $this->assertSame('unavailable',$row->reason);
        $this->assertFalse(str_contains(json_encode($row), 'secret-token'));
    }

    public function test_provider_failure_releases_capacity_and_is_observable_without_error_body(): void {
        global $DB;
        $u=$this->user();\curl::mock_response('{"error":{"code":403,"message":"private-upstream-detail"}}');
        try {live::start((int)$u->id,'Sulafat',true);$this->fail('Expected provider failure');}
        catch (\moodle_exception $e) {$this->assertStringNotContainsString('private-upstream',$e->getMessage());}
        $this->assertSame(0,operations::active());
        $this->assertSame(['provider'=>1],operations::snapshot()['reasons']);
        $this->assertEquals(1,$DB->get_field(gemini::TABLE,'errors',['userid'=>$u->id]));
    }

    public function test_expired_or_unknown_sessions_fail_closed_after_server_restart(): void {
        global $DB;
        $u=$this->user();$s=$this->start((int)$u->id);
        $DB->set_field(operations::TABLE,'expires',time()-31,['userid'=>$u->id]);
        \cache::make(preferences::COMPONENT,'live_sessions')->purge();
        $this->assertFalse(live::report((int)$u->id,$s['session'],10,10,false));
        $this->assertFalse(live::report((int)$u->id,str_repeat('f',32),10,10,false));
        $this->assertSame(0,operations::active());
        $this->assertSame(1,operations::snapshot()['totals']['unclosed']);
    }

    public function test_revoke_or_delete_prevents_old_reports_reopening_sessions(): void {
        global $DB;
        foreach (['revoke','delete_authorization'] as $action) {
            $u=$this->user();$s=$this->start((int)$u->id);
            access::$action((int)$u->id);
            $this->assertFalse(live::enabled((int)$u->id));
            $this->assertFalse(live::report((int)$u->id,$s['session'],999,999,false));
            $this->assertSame('revoked',$DB->get_field(operations::TABLE,'reason',['userid'=>$u->id]));
        }
        $this->assertSame(0,operations::active());
    }

    public function test_retention_and_privacy_deletion_include_operational_data(): void {
        global $DB;
        $u=$this->user();$s=$this->start((int)$u->id);
        $this->assertNotEmpty(privacy\provider::get_contexts_for_userid((int)$u->id)->get_contextids());
        access::erase_user_data((int)$u->id);
        $this->assertFalse($DB->record_exists(operations::TABLE,['userid'=>$u->id]));
        $this->assertFalse(live::report((int)$u->id,$s['session'],1,1,false));
        $other=$this->user();$this->start((int)$other->id);
        $DB->set_field(operations::TABLE,'timecreated',time()-31*DAYSECS,['userid'=>$other->id]);
        operations::purge();
        $this->assertEquals(0,$DB->count_records(operations::TABLE));
    }

    public function test_dashboard_is_aggregate_and_alerts_require_a_minimum_sample(): void {
        $u=$this->user();
        for ($i=0;$i<5;$i++) {
            $id=bin2hex(random_bytes(16));operations::reserve($id,(int)$u->id,0,time()+180);
            live::report((int)$u->id,$id,5,3,true,['connectms'=>6000,'reason'=>'offline']);
            if ($i===0) {$this->assertNotContains('failures',operations::snapshot()['alerts']);}
        }
        $health=operations::snapshot();
        $this->assertContains('failures',$health['alerts']);$this->assertContains('latency',$health['alerts']);
        $this->assertSame(5,$health['totals']['sessions']);$this->assertSame(5,$health['totals']['failures']);
        $text=json_encode($health);
        foreach ([$u->username,'sessionhash','userid','auth_tokens/','synthetic-operational-key'] as $private) {
            $this->assertStringNotContainsString($private,$text);
        }
    }
}
