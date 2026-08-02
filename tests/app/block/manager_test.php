<?php

namespace block_activity_copy_cart\app\block;

use block_activity_copy_cart\exception\exception;


final class manager_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_build_happy_path_with_defaults(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'My page']);
        $cart = manager::build([$page->cmid], []);
        $this->assertEquals($course->id, $cart['sourcecourseid']);
        $this->assertCount(1, $cart['items']);
        $this->assertArrayHasKey($page->cmid, $cart['items']);
        $item = $cart['items'][$page->cmid];
        $this->assertEquals($page->cmid, $item['cmid']);
        $this->assertSame('page', $item['modname']);
        $this->assertSame('My page', $item['name']);
        $this->assertEquals(\context_module::instance($page->cmid)->id, $item['contextid']);
        $this->assertSame('', $item['rename']);
        $this->assertFalse($item['userdata']);
        $this->assertSame(item_settings::SECTION_MATCH_NAME, $item['sectionmatch']);
        $this->assertSame(item_settings::SECTION_MISSING_CREATE, $item['sectionmissing']);
        $this->assertSame(item_settings::NAME_CONFLICT_RESOLVE, $item['nameconflict']);
        $this->assertSame(item_settings::VISIBILITY_SOURCE, $item['visibility']);
        $this->assertTrue($item['restrictions']);
    }

    public function test_build_applies_raw_overrides(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $cart = manager::build([$page->cmid], [
            (string) $page->cmid => [
                'rename' => '  New Name  ',
                'userdata' => 1,
                'sectionmatch' => 'not-a-real-option',
                'section' => 5,
                'sectionname' => 'Custom section',
                'sectionmissing' => item_settings::SECTION_MISSING_SKIP,
                'nameconflict' => item_settings::NAME_CONFLICT_SKIP,
                'visibility' => item_settings::VISIBILITY_HIDE,
                'restrictions' => 0,
            ],
        ]);

        $item = $cart['items'][$page->cmid];
        $this->assertSame('New Name', $item['rename']);
        $this->assertTrue($item['userdata']);
        $this->assertSame(item_settings::SECTION_MATCH_NAME, $item['sectionmatch']);
        $this->assertSame(5, $item['section']);
        $this->assertSame('Custom section', $item['sectionname']);
        $this->assertSame(item_settings::SECTION_MISSING_SKIP, $item['sectionmissing']);
        $this->assertSame(item_settings::NAME_CONFLICT_SKIP, $item['nameconflict']);
        $this->assertSame(item_settings::VISIBILITY_HIDE, $item['visibility']);
        $this->assertFalse($item['restrictions']);
    }

    public function test_build_throws_on_empty_cmids(): void {
        $this->expectException(exception::class);
        manager::build([], []);
    }

    public function test_build_throws_when_cmids_span_multiple_courses(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course2->id]);

        $this->expectException(exception::class);
        manager::build([$page1->cmid, $page2->cmid], []);
    }

    public function test_build_skips_nonexistent_cmid(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $bogus = $page->cmid + 999999;

        $cart = manager::build([$page->cmid, $bogus], []);

        $this->assertCount(1, $cart['items']);
        $this->assertArrayHasKey($page->cmid, $cart['items']);
        $this->assertArrayNotHasKey($bogus, $cart['items']);
    }

    public function test_build_throws_cartempty_when_all_cmids_invalid(): void {
        $this->expectException(exception::class);
        manager::build([999999], []);
    }

    public function test_rows_reports_badges_for_non_default_settings(): void {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $cart = manager::build([$page->cmid], [
            (string) $page->cmid => [
                'rename' => 'Renamed page',
                'visibility' => item_settings::VISIBILITY_HIDE,
                'userdata' => 1,
            ],
        ]);

        $rows = manager::rows($cart);
        $this->assertCount(1, $rows);
        $this->assertSame('Renamed page', $rows[0]['name']);
        $this->assertSame([
            get_string('badgerenamed', 'block_activity_copy_cart', 'Renamed page'),
            get_string('visibilityhide', 'block_activity_copy_cart'),
            get_string('includeuserdata', 'block_activity_copy_cart'),
        ], $rows[0]['badges']);
    }

    public function test_item_rows_skips_deleted_activity(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cart = manager::build([$page->cmid], []);
        set_config('coursebinenable', 0, 'tool_recyclebin');
        course_delete_module($page->cmid);
        rebuild_course_cache($course->id, true);

        $this->assertSame([], manager::item_rows($cart));
        $this->assertSame('', manager::hidden_fields_html($cart));
    }
}
