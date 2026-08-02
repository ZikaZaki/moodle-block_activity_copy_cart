<?php

namespace block_activity_copy_cart\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_activity_copy_cart\app\target\courses_tree;
use block_activity_copy_cart\traits\course_authorization;


class get_tree_node extends external_api {
    use course_authorization;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecourseid' => new external_value(PARAM_INT, 'The cart\'s source course, always excluded from results'),
            'categoryid' => new external_value(PARAM_INT, 'The category to expand, or 0 for the top level'),
        ]);
    }

    public static function execute(int $sourcecourseid, int $categoryid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecourseid' => $sourcecourseid,
            'categoryid' => $categoryid,
        ]);

        self::authorize_course($params['sourcecourseid']);

        return courses_tree::children($params['categoryid'], $params['sourcecourseid']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Category id'),
                    'name' => new external_value(PARAM_RAW, 'Formatted category name'),
                    'haschildren' => new external_value(PARAM_BOOL, 'Whether this category has subcategories or courses'),
                ])
            ),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course id'),
                    'fullname' => new external_value(PARAM_RAW, 'Formatted course full name'),
                ])
            ),
        ]);
    }
}
