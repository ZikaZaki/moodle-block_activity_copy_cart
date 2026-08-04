<?php

namespace block_activity_copy_cart\app\target;


final class courses_tree_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Forces a fresh read of category hierarchy data (path, parent/child, tree listings)
     * instead of whatever core_course_category's own caches (coursecat, coursecatrecords,
     * coursecattree) currently hold - core's own test suite does the same
     * (course/tests/category_test.php) when a test's assertions depend on a category's
     * hierarchy reflecting a change made moments earlier in the same test.
     */
    private function purge_category_caches(): void {
        \cache_helper::purge_by_definition('core', 'coursecat');
        \cache_helper::purge_by_definition('core', 'coursecatrecords');
        \cache_helper::purge_by_definition('core', 'coursecattree');
    }

    public function test_filter_excludes_source_and_invalid_ids(): void {
        $source = $this->getDataGenerator()->create_course();
        $target1 = $this->getDataGenerator()->create_course();
        $target2 = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($target1, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $target2->id, 'editingteacher');
        $this->setUser($teacher);

        $result = courses_tree::filter(
            [$target1->id, $target2->id, $target2->id, $source->id, 0, -1, 999999],
            $source->id
        );

        $this->assertEqualsCanonicalizing([$target1->id, $target2->id], $result);
    }

    public function test_filter_excludes_course_without_target_capability(): void {
        $source = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($target, 'student');
        $this->setUser($student);

        $this->assertSame([], courses_tree::filter([$target->id], $source->id));
    }

    public function test_expand_categories_recurses_and_dedupes(): void {
        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $courseinparent = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $courseinchild = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $this->purge_category_caches();

        $result = courses_tree::expand_categories([$parent->id, $parent->id, 0, -1, 999999]);

        $this->assertEqualsCanonicalizing([$courseinparent->id, $courseinchild->id], $result);
    }

    public function test_expand_categories_empty_category(): void {
        $empty = $this->getDataGenerator()->create_category();

        $this->assertSame([], courses_tree::expand_categories([$empty->id]));
    }

    public function test_ancestor_path(): void {
        $top = $this->getDataGenerator()->create_category();
        $mid = $this->getDataGenerator()->create_category(['parent' => $top->id]);
        $leaf = $this->getDataGenerator()->create_category(['parent' => $mid->id]);
        $this->purge_category_caches();
        $this->assertSame([], courses_tree::ancestor_path($top->id));
        $this->assertEquals([$top->id], courses_tree::ancestor_path($mid->id));
        $this->assertEquals([$top->id, $mid->id], courses_tree::ancestor_path($leaf->id));
    }

    public function test_ancestor_path_unknown_category(): void {
        $this->assertSame([], courses_tree::ancestor_path(999999));
    }

    public function test_restore_paths(): void {
        $top = $this->getDataGenerator()->create_category();
        $sub = $this->getDataGenerator()->create_category(['parent' => $top->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $sub->id]);
        $deletedcourseid = $course->id + 999999;
        $this->purge_category_caches();

        $result = courses_tree::restore_paths([$course->id, $deletedcourseid], [$top->id]);

        $this->assertEquals([
            ['id' => $course->id, 'path' => [$top->id, $sub->id]],
        ], $result['courses']);
        $this->assertEquals([
            ['id' => $top->id, 'path' => []],
        ], $result['categories']);
    }

    public function test_children_lists_courses_in_category(): void {
        $source = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $allowedcourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $blockedcourse = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $teacher = $this->getDataGenerator()->create_and_enrol($allowedcourse, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $blockedcourse->id, 'student');
        $this->setUser($teacher);

        $result = courses_tree::children($category->id, $source->id);

        $courseids = array_column($result['courses'], 'id');
        $this->assertContains($allowedcourse->id, $courseids);
        $this->assertNotContains($blockedcourse->id, $courseids);
        $this->assertNotContains($source->id, $courseids);
    }

    public function test_children_at_top_level(): void {
        $source = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->purge_category_caches();

        $result = courses_tree::children(0, $source->id);

        $categoryrow = null;
        foreach ($result['categories'] as $row) {
            if ($row['id'] === $category->id) {
                $categoryrow = $row;
            }
        }
        $this->assertNotNull($categoryrow, 'The freshly created category must appear at the top level.');
        $this->assertTrue($categoryrow['haschildren']);
    }

    public function test_children_unknown_category(): void {
        $source = $this->getDataGenerator()->create_course();

        $this->assertSame(['categories' => [], 'courses' => []], courses_tree::children(999999, $source->id));
    }
}
