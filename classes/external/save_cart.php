<?php

namespace block_activity_copy_cart\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_activity_copy_cart\app\block\manager;
use block_activity_copy_cart\app\block\repository;
use block_activity_copy_cart\exception\exception;
use block_activity_copy_cart\traits\course_authorization;


class save_cart extends external_api {
    use course_authorization;

    /** @var int Maximum cart items accepted in one autosave call. */
    private const MAX_ITEMS = 200;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(
                PARAM_INT,
                'The course the block instance lives on - only used to authorize an empty (clearing) save, ' .
                'since a non-empty one is authorized against the course its own activities actually belong to'
            ),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'),
                'Every activity currently in the cart, empty to clear it',
                VALUE_DEFAULT,
                []
            ),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'rename' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
                    'sectionmatch' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, ''),
                    // -1: never a real section number, distinguishes "not explicitly sent" from
                    // "explicitly sent as 0" - see manager::build()'s own section-resolving line.
                    'section' => new external_value(PARAM_INT, '', VALUE_DEFAULT, -1),
                    'sectionname' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
                    'sectionmissing' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, ''),
                    'nameconflict' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, ''),
                    'visibility' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, ''),
                    'restrictions' => new external_value(PARAM_BOOL, '', VALUE_DEFAULT, true),
                ]),
                'Per-cmid settings, one entry per id in cmids',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(int $courseid, array $cmids, array $items): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'items' => $items,
        ]);

        if (empty($params['cmids'])) {
            self::authorize_course($params['courseid']);
            repository::clear();
            return ['result' => true];
        }

        if (count($params['cmids']) > self::MAX_ITEMS || count($params['items']) > self::MAX_ITEMS) {
            debugging('block_activity_copy_cart: skipped cart autosave - too many items submitted', DEBUG_DEVELOPER);
            return ['result' => false];
        }

        try {
            // Authorize against the real source course before paying for build()'s more
            // expensive per-item hydration (get_fast_modinfo(), name/icon formatting) - resolving
            // it is cheap (one query), so this closes the gap where an unauthorized caller could
            // otherwise make this endpoint do that work on every probe.
            self::authorize_course(manager::resolve_source_course($params['cmids']));
        } catch (exception $e) {
            debugging('block_activity_copy_cart: skipped cart autosave - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['result' => false];
        }

        $rawitems = [];
        foreach ($params['items'] as $item) {
            $rawitems[(string) $item['cmid']] = $item;
        }

        try {
            $cart = manager::build($params['cmids'], $rawitems);
        } catch (exception $e) {
            debugging('block_activity_copy_cart: skipped cart autosave - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['result' => false];
        }

        repository::save($cart);
        return ['result' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(
                PARAM_BOOL,
                'Whether the cart was actually saved (false if it was skipped as stale/invalid)'
            ),
        ]);
    }
}
