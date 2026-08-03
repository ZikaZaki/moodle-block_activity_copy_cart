<?php

namespace block_activity_copy_cart\external;

use block_activity_copy_cart\app\block\repository;
use core_external\external_api;


final class save_cart_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function execute(int $courseid, array $cmids, array $items): array {
        $result = save_cart::execute($courseid, $cmids, $items);
        return external_api::clean_returnvalue(save_cart::execute_returns(), $result);
    }

    public function test_saves_a_valid_single_course_cart(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = $this->execute($course->id, [$page->cmid], [['cmid' => $page->cmid]]);

        $this->assertTrue($result['result']);
        $saved = repository::get();
        // manager::build() explicitly casts sourcecourseid to (int); the generator's own
        // ->id is not guaranteed to be typed the same way, so compare the numeric value only.
        $this->assertSame((int) $course->id, $saved['sourcecourseid']);
        $this->assertArrayHasKey($page->cmid, $saved['items']);
    }

    public function test_clears_the_cart_when_cmids_is_empty(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = $this->execute($course->id, [], []);

        $this->assertTrue($result['result']);
        $this->assertNull(repository::get());
    }

    public function test_returns_false_for_cmids_spanning_multiple_courses(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course2->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course1, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'editingteacher');
        $this->setUser($teacher);

        $result = $this->execute($course1->id, [$page1->cmid, $page2->cmid], [
            ['cmid' => $page1->cmid],
            ['cmid' => $page2->cmid],
        ]);

        $this->assertFalse($result['result']);
        $this->assertDebuggingCalled();
    }

    public function test_returns_false_when_too_many_items_submitted(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $cmids = range(1, 201);
        $items = array_map(fn(int $cmid): array => ['cmid' => $cmid], $cmids);

        $result = $this->execute($course->id, $cmids, $items);

        $this->assertFalse($result['result']);
        $this->assertDebuggingCalled();
    }

    public function test_throws_without_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        save_cart::execute($course->id, [$page->cmid], [['cmid' => $page->cmid]]);
    }
}
