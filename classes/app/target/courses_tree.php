<?php

namespace block_activity_copy_cart\app\target;

use block_activity_copy_cart\exception\exception;


final class courses_tree {
    private const TARGET_CAPABILITY = 'moodle/restore:restoretargetimport';

    /**
     * Maximum courses returned for one category level of the tree - bounds how much a single
     * "expand category" click can force the server to fetch/render/send in one response
     * (teachers with very large categories still have the search feature, which is already
     * paginated at RESULTS_PER_PAGE in target/search.js).
     *
     * @var int
     */
    private const MAX_COURSES_PER_LEVEL = 200;

    /**
     * Maximum courses a single target selection (individually picked + whole-category expansions
     * combined) may resolve to - protects the cron task queue from one "whole category" pick
     * enqueueing an unbounded number of restore units (see copy\manager::MAX_TOTAL_UNITS for the
     * other half of this cap, applied to items x courses at job-creation time).
     *
     * @var int
     */
    private const MAX_TARGET_COURSES = 1000;

    public static function children(int $categoryid, int $sourcecourseid): array {
        $category = $categoryid > 0
            ? \core_course_category::get($categoryid, IGNORE_MISSING)
            : \core_course_category::top();

        if (!$category) {
            return ['categories' => [], 'courses' => []];
        }

        return [
            'categories' => self::category_rows($category),
            'courses' => self::course_rows($category, $sourcecourseid),
        ];
    }

    private static function category_rows(\core_course_category $category): array {
        $rows = [];
        foreach ($category->get_children() as $child) {
            $haschildren = $child->get_children_count() > 0 || $child->get_courses_count() > 0;

            $rows[] = [
                'id' => $child->id,
                'name' => $child->get_formatted_name(),
                'haschildren' => $haschildren,
            ];
        }
        return $rows;
    }

    private static function course_rows(\core_course_category $category, int $sourcecourseid): array {
        $rows = [];
        foreach ($category->get_courses(['recursive' => false, 'limit' => self::MAX_COURSES_PER_LEVEL]) as $course) {
            if ($course->id == $sourcecourseid) {
                continue;
            }
            $context = \context_course::instance($course->id);
            if (!has_capability(self::TARGET_CAPABILITY, $context)) {
                continue;
            }
            $rows[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
            ];
        }
        return $rows;
    }

    public static function ancestor_path(int $categoryid): array {
        $category = \core_course_category::get($categoryid, IGNORE_MISSING);
        if (!$category) {
            return [];
        }
        return array_map('intval', $category->get_parents());
    }

    public static function restore_paths(array $courseids, array $categoryids): array {
        global $DB;

        $courses = [];
        if (!empty($courseids)) {
            $categoryidsbycourse = $DB->get_records_list('course', 'id', $courseids, '', 'id, category');
            foreach ($courseids as $courseid) {
                if (!isset($categoryidsbycourse[$courseid])) {
                    // Deleted since it was saved - nothing to restore.
                    continue;
                }
                $categoryid = (int) $categoryidsbycourse[$courseid]->category;
                $courses[] = [
                    'id' => $courseid,
                    'path' => array_merge(self::ancestor_path($categoryid), [$categoryid]),
                ];
            }
        }

        $categories = [];
        foreach ($categoryids as $categoryid) {
            $categories[] = [
                'id' => $categoryid,
                'path' => self::ancestor_path($categoryid),
            ];
        }

        return ['courses' => $courses, 'categories' => $categories];
    }

    /**
     * Recursively expands a set of "whole category" picks into their individual course ids.
     *
     * @param array $categoryids
     * @return array
     * @throws exception If the expansion resolves to more than MAX_TARGET_COURSES courses
     */
    public static function expand_categories(array $categoryids): array {
        $categoryids = array_unique(array_map('intval', $categoryids));
        $categoryids = array_filter($categoryids, fn($id) => $id > 0);

        $courseids = [];
        foreach ($categoryids as $categoryid) {
            $category = \core_course_category::get($categoryid, IGNORE_MISSING);
            if (!$category) {
                continue;
            }
            $courseids[] = $category->get_courses(['recursive' => true, 'idonly' => true]);
        }

        $expanded = empty($courseids) ? [] : array_values(array_unique(array_merge(...$courseids)));
        if (count($expanded) > self::MAX_TARGET_COURSES) {
            throw new exception('errortoomanytargetcourses', self::MAX_TARGET_COURSES);
        }
        return $expanded;
    }

    public static function filter(array $courseids, int $sourcecourseid): array {
        global $DB;

        $courseids = array_unique(array_map('intval', $courseids));
        $courseids = array_values(array_filter($courseids, function ($id) use ($sourcecourseid) {
            return $id > 0 && $id !== $sourcecourseid;
        }));
        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $existing = $DB->get_records_select('course', "id $insql", $inparams, '', 'id');

        $valid = [];
        foreach (array_keys($existing) as $id) {
            if (has_capability('moodle/restore:restoretargetimport', \context_course::instance($id))) {
                $valid[] = $id;
            }
        }
        return $valid;
    }
}
