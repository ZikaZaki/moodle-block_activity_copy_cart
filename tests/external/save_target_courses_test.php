<?php

namespace block_activity_copy_cart\external;

use block_activity_copy_cart\app\target\repository;
use block_activity_copy_cart\exception\exception;


final class save_target_courses_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_saves_the_selection(): void {
        $course = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = save_target_courses::execute($course->id, [$target->id], [$category->id]);

        $this->assertTrue($result['result']);
        $saved = repository::get($course->id);
        // repository::save() explicitly casts every id to (int) via array_map('intval', ...);
        // the generator's own ->id is not guaranteed to be typed the same way.
        $this->assertSame([(int) $target->id], $saved['courseids']);
        $this->assertSame([(int) $category->id], $saved['categoryids']);
    }

    public function test_throws_when_too_many_ids_submitted(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(exception::class);
        save_target_courses::execute($course->id, range(1, 1001), []);
    }

    public function test_throws_without_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        save_target_courses::execute($course->id, [], []);
    }
}
