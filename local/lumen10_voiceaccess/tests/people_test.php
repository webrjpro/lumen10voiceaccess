<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess;
defined('MOODLE_INTERNAL') || die();

final class people_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }
    public function test_initial_selector_contains_registered_people_without_search(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Maria', 'lastname' => 'Da Silva']);
        $result = people::search();
        $this->assertArrayHasKey($user->id, $result['options']);
        $this->assertStringContainsString('Maria', $result['options'][$user->id]);
        $this->assertGreaterThan(0, $result['total']);
        $this->assertArrayNotHasKey((int)$CFG->siteguest, $result['options']);
    }
    public function test_search_finds_full_name_username_and_email(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Mariana', 'lastname' => 'Oliveira',
            'username' => 'accessibleperson', 'email' => 'registered-person@example.invalid']);
        foreach (['Mariana Oliveira', 'accessibleperson', 'registered-person@example.invalid'] as $term) {
            $this->assertArrayHasKey($user->id, people::search($term)['options']);
        }
        $this->assertSame(0, people::search('not-a-person-anywhere')['total']);
    }
    public function test_deleted_and_suspended_accounts_are_not_selectable(): void {
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user(['deleted' => 1]);
        $result = people::search();
        $this->assertArrayNotHasKey($suspended->id, $result['options']);
        $this->assertArrayNotHasKey($deleted->id, $result['options']);
    }
    public function test_pagination_reaches_people_beyond_first_page_and_preserves_selection(): void {
        $g = $this->getDataGenerator();
        for ($i = 0; $i <= people::PAGE_SIZE; $i++) {
            $user = $g->create_user(['firstname' => 'CatalogTest', 'lastname' => sprintf('%03d', $i)]);
        }
        $first = people::search('CatalogTest');
        $second = people::search('CatalogTest', 1);
        $this->assertSame(people::PAGE_SIZE + 1, $first['total']);
        $this->assertArrayNotHasKey($user->id, $first['options']);
        $this->assertArrayHasKey($user->id, $second['options']);
        $this->assertArrayHasKey($user->id, people::search('CatalogTest', 0, $user->id)['options']);
        $this->assertSame(1, people::search('CatalogTest', 999)['page']);
    }
    public function test_ordinary_participant_cannot_enumerate_registered_people(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        people::search();
    }
}
