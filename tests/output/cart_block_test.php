<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\block\manager as cart_manager;
use block_activity_copy_cart\app\block\repository;


final class cart_block_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_export_for_template_without_a_cart(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();

        $data = (new cart_block($course->id))->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('', $data['cartitemshtml']);
        $this->assertSame('', $data['hiddenfieldshtml']);
        $this->assertSame($course->id, $data['courseid']);
    }

    public function test_export_for_template_with_a_matching_cart(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'My page']);
        repository::save(cart_manager::build([$page->cmid], []));

        $data = (new cart_block($course->id))->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('My page', $data['cartitemshtml']);
        $this->assertNotSame('', $data['hiddenfieldshtml']);
    }

    public function test_export_for_template_ignores_a_cart_from_a_different_course(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $othercourse->id]);
        repository::save(cart_manager::build([$page->cmid], []));

        $data = (new cart_block($course->id))->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('', $data['cartitemshtml']);
    }
}
