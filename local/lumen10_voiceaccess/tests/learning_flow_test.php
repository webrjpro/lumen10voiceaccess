<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

final class learning_flow_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, preferences::COMPONENT);
        set_config('version', 2026101601, 'mod_lumen10voiceaccess');
        set_config('enablecompletion', 1);
    }

    private function learner(): \stdClass {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        access::grant($user->id, 'blind', true);
        $this->setUser($user);
        return $user;
    }

    public function test_only_approved_learners_receive_simplified_navigation(): void {
        $user = $this->learner();
        $this->assertTrue(learning_flow::enabled());
        $this->setAdminUser();
        access::grant($user->id, 'severemobility', true);
        $this->setUser($user);
        $this->assertTrue(learning_flow::enabled());
        $this->setAdminUser();
        access::revoke($user->id);
        $this->setUser($user);
        $this->assertFalse(learning_flow::enabled());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(learning_flow::enabled());
        $this->setAdminUser();
        $this->assertFalse(learning_flow::enabled());
    }

    public function test_empty_enrolments_do_not_expose_public_catalogue_and_suspension_is_respected(): void {
        global $DB;
        $user = $this->learner();
        $g = $this->getDataGenerator();
        $public = $g->create_course();
        $hidden = $g->create_course(['visible' => 0]);
        $this->assertSame([], learning_flow::courses());
        $this->assertSame([], learning_flow::priorities());
        $g->enrol_user($user->id, $public->id, 'student');
        $g->enrol_user($user->id, $hidden->id, 'student');
        $this->assertSame([(int)$public->id], array_map('intval', array_keys(learning_flow::courses())));
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $user->id]);
        $this->assertSame([], learning_flow::courses());
    }

    public function test_course_order_uses_last_access_without_changing_teacher_activity_order(): void {
        global $DB;
        $user = $this->learner();$g = $this->getDataGenerator();
        $old = $g->create_course(['enablecompletion' => 1]);
        $recent = $g->create_course(['enablecompletion' => 1]);
        $g->enrol_user($user->id, $old->id, 'student');$g->enrol_user($user->id, $recent->id, 'student');
        $DB->insert_record('user_lastaccess', (object)['userid' => $user->id, 'courseid' => $recent->id, 'timeaccess' => time()]);
        $first = $g->create_module('page', ['course' => $recent->id, 'name' => 'First lesson', 'completion' => COMPLETION_TRACKING_MANUAL]);
        $second = $g->create_module('page', ['course' => $recent->id, 'name' => 'Second lesson']);
        $g->create_module('page', ['course' => $recent->id, 'name' => 'Hidden lesson', 'visible' => 0]);
        $g->create_module('page', ['course' => $recent->id, 'name' => 'Locked lesson', 'availability' => json_encode([
            'op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]], 'showc' => [false],
        ])]);
        $info = get_fast_modinfo($recent, $user->id);
        $completion = new \completion_info($recent);
        $completion->update_state($info->get_cm($first->cmid), COMPLETION_COMPLETE, $user->id);
        $this->assertEquals($recent->id, array_key_first(learning_flow::courses()));
        $items = learning_flow::activities($recent);
        $this->assertSame(['First lesson', 'Second lesson'], array_column($items, 'name'));
        $this->assertTrue($items[0]['complete']);
        $priorities = learning_flow::priorities();
        $this->assertSame('Second lesson', $priorities[0]['name']);
        $this->assertEquals($second->cmid, $priorities[0]['id']);
    }

    public function test_native_action_deadlines_are_ordered_and_inaccessible_items_excluded(): void {
        $user = $this->learner();$g = $this->getDataGenerator();$now = time();
        $course = $g->create_course(['enablecompletion' => 1]);$g->enrol_user($user->id, $course->id, 'student');
        $g->create_module('page', ['course' => $course->id, 'name' => 'Continue lesson']);
        $later = $g->create_module('assign', ['course' => $course->id, 'name' => 'Later task', 'duedate' => $now + 3 * DAYSECS,
            'assignsubmission_onlinetext_enabled' => 1]);
        $soon = $g->create_module('assign', ['course' => $course->id, 'name' => 'Soon task', 'duedate' => $now + DAYSECS,
            'assignsubmission_onlinetext_enabled' => 1]);
        $g->create_module('assign', ['course' => $course->id, 'name' => 'Hidden due task', 'duedate' => $now + 30, 'visible' => 0]);
        $other = $g->create_course();
        $g->create_module('assign', ['course' => $other->id, 'name' => 'Other course secret', 'duedate' => $now + 10]);
        $items = learning_flow::priorities($now);
        $this->assertSame(['Soon task', 'Later task', 'Continue lesson'], array_column($items, 'name'));
        $this->assertEquals([$soon->cmid, $later->cmid], array_column(array_slice($items, 0, 2), 'id'));
        $this->assertFalse(learning_flow::deadline_unavailable());
    }

    public function test_unfinished_quiz_precedes_deadlines_and_is_not_duplicated(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $user = $this->learner();$g = $this->getDataGenerator();
        $course = $g->create_course();$g->enrol_user($user->id, $course->id, 'student');
        $quiz = $g->create_module('quiz', ['course' => $course->id, 'name' => 'Ongoing quiz', 'timeclose' => time() + 3 * DAYSECS]);
        $questiong = $g->get_plugin_generator('core_question');
        $category = $questiong->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questiong->create_question('multichoice', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $g->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $user->id);
        $g->create_module('assign', ['course' => $course->id, 'name' => 'Near deadline', 'duedate' => time() + HOURSECS,
            'assignsubmission_onlinetext_enabled' => 1]);
        $items = learning_flow::priorities();
        $this->assertSame('Ongoing quiz', $items[0]['name']);
        $this->assertSame(0, $items[0]['priority']);
        $this->assertCount(1, array_filter($items, static fn($i) => $i['name'] === 'Ongoing quiz'));
        $this->assertSame('Near deadline', $items[1]['name']);
        $page = new \moodle_page();$page->set_context(\context_system::instance());
        $page->set_course(get_course(SITEID));$page->set_url(learning_flow::url());
        $page->set_pagetype('local-lumen10_voiceaccess-learn');
        $snapshot = learning_flow::navigation($page);
        $this->assertSame($items[0]['url'], $snapshot['activities'][0]['url']);
        $this->assertSame('Ongoing quiz', $snapshot['activities'][0]['name']);
    }

    public function test_entry_routing_does_not_capture_assessment_or_other_controllers(): void {
        $this->assertSame((new \moodle_url('/local/lumen10_voiceaccess/learn.php'))->get_path(),
            learning_flow::entry_target('my-index')->get_path());
        $this->assertSame('courses', learning_flow::entry_target('my-courses')->get_param('view'));
        $this->assertSame('42', learning_flow::entry_target('course-view-topics', 42)->get_param('id'));
        foreach (['mod-quiz-attempt', 'mod-quiz-processattempt', 'mod-quiz-summary', 'mod-assign-view', 'admin-index',
                'local-lumen10_voiceaccess-learn'] as $type) {
            $this->assertNull(learning_flow::entry_target($type, 42));
        }
    }

    public function test_student_read_pages_keep_accessible_navigation_without_capturing_admin_or_secure_pages(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (['/grade/report/overview/index.php', '/calendar/view.php', '/message/index.php', '/user/profile.php'] as $path) {
            $page = new \moodle_page();
            $page->set_context(\context_system::instance());
            $page->set_url($path);
            $page->set_pagelayout('standard');
            $this->assertTrue(learning_flow::supports_page($page), $path);
        }
        $page = new \moodle_page();$page->set_context(\context_system::instance());
        $page->set_url('/admin/index.php');$page->set_pagelayout('standard');
        $this->assertFalse(learning_flow::supports_page($page));
        $page = new \moodle_page();$page->set_context(\context_system::instance());
        $page->set_url('/mod/quiz/attempt.php');$page->set_pagelayout('secure');
        $this->assertFalse(learning_flow::supports_page($page));
    }
}
