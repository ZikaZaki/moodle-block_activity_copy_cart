<?php

namespace block_activity_copy_cart\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_activity_copy_cart\app\copy\manager;
use block_activity_copy_cart\app\copy\repository;


class get_job_progress extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'The copy job id'),
        ]);
    }

    public static function execute(int $jobid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['jobid' => $jobid]);

        self::validate_context(\context_system::instance());

        global $USER;
        $job = manager::require_owned_job($params['jobid'], (int) $USER->id);

        $context = manager::job_context($job);
        $context['results'] = manager::result_rows($job, repository::get_results($job->id));

        return $context;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_INT, 'Job id'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Raw job status'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Localized job status'),
            'completedunits' => new external_value(PARAM_INT, 'Units processed so far'),
            'totalunits' => new external_value(PARAM_INT, 'Total units to process'),
            'percent' => new external_value(PARAM_INT, 'Completion percentage (0-100)'),
            'isterminal' => new external_value(PARAM_BOOL, 'Whether the job has reached a terminal state'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'activityname' => new external_value(PARAM_RAW, 'Formatted source activity name'),
                    'coursefullname' => new external_value(PARAM_RAW, 'Formatted target course full name'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Localized result status'),
                    'message' => new external_value(PARAM_TEXT, 'Why it was skipped/failed, empty for success'),
                ])
            ),
        ]);
    }
}
