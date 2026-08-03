<?php

namespace block_activity_copy_cart\external;


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

        $result = get_tree_node::execute($sourcecourse->id, $category->id);

        $this->assertCount(1, $result['courses']);
        $this->assertSame($targetcourse->id, $result['courses'][0]['id']);
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
