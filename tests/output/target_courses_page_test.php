<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\block\manager as cart_manager;


final class target_courses_page_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_export_for_template(): void {
        global $OUTPUT;
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'My page']);
        $cart = cart_manager::build([$page->cmid], []);
        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $pageurl = new \moodle_url('/blocks/activity_copy_cart/target_courses.php');
        $targetrows = [['id' => 5, 'fullname' => 'Target course']];

        $data = (new target_courses_page($courseurl, $pageurl, true, $cart, $targetrows, ''))
            ->export_for_template($OUTPUT);

        $this->assertTrue($data['reviewing']);
        $this->assertSame(1, $data['cartcount']);
        $this->assertSame(1, $data['targetcount']);
        $this->assertSame($targetrows, $data['targetcourses']);
        $this->assertSame($courseurl->out(false), $data['courseurl']);
    }
}
