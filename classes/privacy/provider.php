<?php

namespace block_activity_copy_cart\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use block_activity_copy_cart\app\copy\repository;


class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_activity_copy_cart_job', [
            'userid' => 'privacy:metadata:job:userid',
            'sourcecourseid' => 'privacy:metadata:job:sourcecourseid',
            'cart' => 'privacy:metadata:job:cart',
            'targetcourseids' => 'privacy:metadata:job:targetcourseids',
            'status' => 'privacy:metadata:job:status',
            'timecreated' => 'privacy:metadata:job:timecreated',
            'timemodified' => 'privacy:metadata:job:timemodified',
        ], 'privacy:metadata:job');

        $collection->add_database_table('block_activity_copy_cart_bkp', [
            'jobid' => 'privacy:metadata:jobbackup:jobid',
            'sourcecmid' => 'privacy:metadata:jobbackup:sourcecmid',
            'backupid' => 'privacy:metadata:jobbackup:backupid',
            'status' => 'privacy:metadata:jobbackup:status',
            'message' => 'privacy:metadata:jobbackup:message',
            'timecleaned' => 'privacy:metadata:jobbackup:timecleaned',
        ], 'privacy:metadata:jobbackup');

        $collection->add_database_table('block_activity_copy_cart_res', [
            'jobid' => 'privacy:metadata:jobresult:jobid',
            'sourcecmid' => 'privacy:metadata:jobresult:sourcecmid',
            'targetcourseid' => 'privacy:metadata:jobresult:targetcourseid',
            'newcmid' => 'privacy:metadata:jobresult:newcmid',
            'status' => 'privacy:metadata:jobresult:status',
            'message' => 'privacy:metadata:jobresult:message',
            'timecreated' => 'privacy:metadata:jobresult:timecreated',
        ], 'privacy:metadata:jobresult');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('block_activity_copy_cart_job', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int) $context->instanceid !== $userid) {
                continue;
            }

            $jobs = [];
            foreach (repository::get_jobs_for_user($userid) as $job) {
                $jobs[] = (object) [
                    'sourcecourseid' => $job->sourcecourseid,
                    'targetcourseids' => json_decode($job->targetcourseids),
                    'itemcount' => count(json_decode($job->cart, true)['items'] ?? []),
                    'status' => $job->status,
                    'completedunits' => $job->completedunits,
                    'totalunits' => $job->totalunits,
                    'timecreated' => transform::datetime($job->timecreated),
                    'timemodified' => transform::datetime($job->timemodified),
                    // Every per-unit result belongs to this job (and thus this user) just as
                    // much as the job row itself does - export it too, so an export actually
                    // covers everything delete_data_for_user() removes.
                    'results' => array_map(
                        fn(\stdClass $result): object => (object) [
                            'sourcecmid' => $result->sourcecmid,
                            'targetcourseid' => $result->targetcourseid,
                            'newcmid' => $result->newcmid,
                            'status' => $result->status,
                            'message' => $result->message,
                            'timecreated' => transform::datetime($result->timecreated),
                        ],
                        repository::get_results($job->id)
                    ),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_activity_copy_cart')],
                (object) ['jobs' => $jobs]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        repository::delete_jobs_for_user((int) $context->instanceid);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER) {
                continue;
            }
            repository::delete_jobs_for_user($userid);
        }
    }
}
