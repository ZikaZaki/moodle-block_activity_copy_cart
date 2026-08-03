<?php

namespace block_activity_copy_cart\external;

use core_external\external_api;


final class get_tree_node_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_lists_courses_the_user_can_restore_into(): void {
        $sourcecourse = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $targetcourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($sourcecourse, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $targetcourse->id, 'editingteacher');
        $this->setUser($teacher);

        $raw = get_tree_node::execute($sourcecourse->id, $category->id);
        // Routed through clean_returnvalue(), like a real web service call - courses_tree.php's
        // own 'id' => $course->id isn't explicitly cast, so calling execute() directly could
        // return a differently-typed id than what PARAM_INT guarantees to a real caller.
        $result = external_api::clean_returnvalue(get_tree_node::execute_returns(), $raw);

        $this->assertCount(1, $result['courses']);
        $this->assertSame((int) $targetcourse->id, $result['courses'][0]['id']);
    }

    public function test_excludes_the_source_course_itself(): void {
        $category = $this->getDataGenerator()->create_category();
        $sourcecourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($sourcecourse, 'editingteacher');
        $this->setUser($teacher);

        $result = get_tree_node::execute($sourcecourse->id, $category->id);

        $this->assertSame([], $result['courses']);
    }

    public function test_throws_without_capability_on_source_course(): void {
        $sourcecourse = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($sourcecourse, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_tree_node::execute($sourcecourse->id, 0);
    }
}
