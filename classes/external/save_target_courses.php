<?php

namespace block_activity_copy_cart\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_activity_copy_cart\app\target\repository;
use block_activity_copy_cart\exception\exception;
use block_activity_copy_cart\traits\course_authorization;


class save_target_courses extends external_api {
    use course_authorization;

    /**
     * @var int Maximum course/category ids accepted in one autosave call - matches
     *  target\courses_tree::MAX_TARGET_COURSES, the cap applied to the same conceptual
     *  "how many targets can one selection resolve to" limit at confirm time.
     */
    private const MAX_IDS = 1000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecourseid' => new external_value(PARAM_INT, 'The cart\'s source course'),
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Individually picked course ids',
                VALUE_DEFAULT,
                []
            ),
            'categoryids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Category id'),
                '"Whole category" picks',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(int $sourcecourseid, array $courseids, array $categoryids): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecourseid' => $sourcecourseid,
            'courseids' => $courseids,
            'categoryids' => $categoryids,
        ]);

        self::authorize_course($params['sourcecourseid']);

        if (count($params['courseids']) > self::MAX_IDS || count($params['categoryids']) > self::MAX_IDS) {
            throw new exception('errortoomanyitems', self::MAX_IDS);
        }

        repository::save($params['sourcecourseid'], $params['courseids'], $params['categoryids']);

        return ['result' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'Always true - throws instead of returning false on failure'),
        ]);
    }
}
