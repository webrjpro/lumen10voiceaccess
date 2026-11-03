<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace mod_lumen10voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** Exercise the activity's persistence and course backup boundary with synthetic data. */
final class lifecycle_test extends \advanced_testcase {
    private function delete_activity(int $courseid, int $cmid): void {
        if (method_exists(\core_courseformat\formatactions::class, 'cm')) {
            \core_courseformat\formatactions::cm($courseid)->delete($cmid);
        } else {
            course_delete_module($cmid);
        }
    }

    private function duplicate_activity(\stdClass $course, \stdClass $cm) {
        if (method_exists(\core_courseformat\formatactions::class, 'cm')) {
            return \core_courseformat\formatactions::cm($course->id)->duplicate($cm->id);
        }
        return duplicate_module($course, $cm);
    }

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/lumen10voiceaccess/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');
    }

    public function test_create_update_and_delete_do_not_leave_an_activity_record(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('lumen10voiceaccess', [
            'course' => $course->id, 'name' => 'Entrada acessível', 'intro' => '<p>Orientação</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $record = $DB->get_record('lumen10voiceaccess', ['id' => $activity->id], '*', MUST_EXIST);
        $this->assertSame('Entrada acessível', $record->name);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertTrue(lumen10voiceaccess_update_instance((object)[
            'instance' => $record->id, 'name' => 'Entrada revisada',
        ]));
        $this->assertSame('Entrada revisada', $DB->get_field('lumen10voiceaccess', 'name', ['id' => $record->id]));
        $this->delete_activity($course->id, $activity->cmid);
        $this->assertFalse($DB->record_exists('lumen10voiceaccess', ['id' => $record->id]));
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $activity->cmid]));
        $this->assertFalse(lumen10voiceaccess_delete_instance($record->id));
    }

    public function test_backup_restore_preserves_intro_files_and_excludes_personal_data(): void {
        global $DB;
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $activity = $g->create_module('lumen10voiceaccess', ['course' => $course->id,
            'name' => 'Guia acessível', 'intro' => '<p>Guia <img src="@@PLUGINFILE@@/guide.svg" alt="Guia"></p>',
            'introformat' => FORMAT_HTML]);
        $context = \context_module::instance($activity->cmid);
        get_file_storage()->create_file_from_string(['contextid' => $context->id,
            'component' => 'mod_lumen10voiceaccess', 'filearea' => 'intro', 'itemid' => 0,
            'filepath' => '/', 'filename' => 'guide.svg'], '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $user = $g->create_user();
        \local_lumen10_voiceaccess\access::grant($user->id, 'blind', true);
        \local_lumen10_voiceaccess\access::issue_pair($user->id);
        $before = [];
        foreach (['local_lumen10_voiceaccess_user', 'local_lumen10_voiceaccess_access', 'local_lumen10_voiceaccess_device'] as $table) {
            $before[$table] = $DB->get_records($table, [], 'id');
        }
        $cm = get_coursemodule_from_id('lumen10voiceaccess', $activity->cmid, 0, false, MUST_EXIST);
        $copy = $this->duplicate_activity($course, $cm);
        $this->assertNotEquals($cm->instance, $copy->instance);
        $restored = $DB->get_record('lumen10voiceaccess', ['id' => $copy->instance], '*', MUST_EXIST);
        $this->assertStringContainsString('Guia acessível', $restored->name);
        $this->assertStringContainsString('guide.svg', $restored->intro);
        $copycontext = \context_module::instance($copy->id);
        $this->assertNotFalse(get_file_storage()->get_file($copycontext->id, 'mod_lumen10voiceaccess',
            'intro', 0, '/', 'guide.svg'));
        $this->delete_activity($course->id, $copy->id);
        foreach ($before as $table => $records) {
            $this->assertEquals($records, $DB->get_records($table, [], 'id'));
        }
    }

    public function test_student_cannot_add_activities_and_view_permission_can_be_revoked(): void {
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $activity = $g->create_module('lumen10voiceaccess', ['course' => $course->id]);
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        $context = \context_module::instance($activity->cmid);
        $this->setUser($user);
        $this->assertTrue(has_capability('mod/lumen10voiceaccess:view', $context));
        $this->assertFalse(has_capability('mod/lumen10voiceaccess:addinstance', $context));
        $role = $g->create_role();
        assign_capability('mod/lumen10voiceaccess:view', CAP_PROHIBIT, $role, $context->id);
        role_assign($role, $user->id, $context->id);
        $this->assertFalse(has_capability('mod/lumen10voiceaccess:view', $context));
    }
}
