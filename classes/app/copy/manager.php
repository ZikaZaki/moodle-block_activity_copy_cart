<?php

namespace block_activity_copy_cart\app\copy;

use block_activity_copy_cart\exception\exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


/**
 * Orchestrates a copy job's backup/restore chunks, status, and owner notification.
 */
final class manager {
    /** @var int Cart items backed up per backup_task execution, then it requeues itself for the rest. */
    const BACKUP_CHUNK_SIZE = 5;

    /** @var int (item x course) pairs restored per restore_task execution, then it requeues itself for the rest. */
    const CHUNK_SIZE = 10;

    /**
     * @var int Maximum (cart items x target courses) units a single job may create - protects the
     *  site's adhoc task queue from one "Copy Activities" click enqueueing an unbounded number of
     *  restore units (see target\courses_tree::MAX_TARGET_COURSES for the other half of this cap,
     *  applied to the target-course count alone before it ever reaches here).
     */
    const MAX_TOTAL_UNITS = 5000;

    /**
     * Fetches a job, refusing access to anyone but its owner (or a site admin).
     *
     * @param int $jobid
     * @param int $userid
     * @return job
     * @throws exception If the job doesn't exist or isn't owned by this user
     */
    public static function require_owned_job(int $jobid, int $userid): job {
        $job = repository::get_job($jobid);
        if (!$job || ($job->userid !== $userid && !is_siteadmin())) {
            throw new exception('jobnotfound');
        }
        return $job;
    }

    /**
     * Whether the job's owner still holds the capability to copy out of the source course.
     *
     * @param job $job
     * @return bool
     */
    private static function has_source_capability(job $job): bool {
        return has_capability(
            'block/activity_copy_cart:copyactivities',
            \context_course::instance($job->sourcecourseid),
            $job->userid
        );
    }

    /**
     * Creates a job and queues its first backup_task.
     *
     * @param array $cart As built by \block_activity_copy_cart\app\block\manager::build()
     * @param array $targetcourseids
     * @return int The new job's id
     */
    public static function create_job(array $cart, array $targetcourseids): int {
        global $DB, $USER;

        $totalunits = count($cart['items']) * count($targetcourseids);
        if ($totalunits > self::MAX_TOTAL_UNITS) {
            throw new exception('errorjobtoolarge', self::MAX_TOTAL_UNITS);
        }

        // Atomic: an interruption between any of these three writes would otherwise leave
        // either a job with no backup rows, or a job that never got a task queued for it -
        // both permanently stuck with nothing to ever pick them back up.
        $transaction = $DB->start_delegated_transaction();
        $jobid = repository::create_job((int) $USER->id, (int) $cart['sourcecourseid'], $cart, $targetcourseids);
        repository::create_job_backups($jobid, array_keys($cart['items']));
        self::queue_backup_task($jobid, (int) $USER->id);
        $transaction->allow_commit();

        return $jobid;
    }

    /**
     * Requeues the appropriate next task for every job that's been sitting in a non-terminal
     * status for longer than $stalledafter seconds - recovers jobs whose adhoc task died
     * without a catchable \Throwable (see create_job()'s docblock). Safe to call repeatedly:
     * process_backups()/process_restores() only ever act on rows still marked pending, so
     * requeuing a job that's actually still being (slowly) worked on just adds a harmless
     * extra chunk-processing pass rather than redoing completed work.
     *
     * @param int $stalledafter Seconds of inactivity before a non-terminal job counts as stalled
     * @return int How many jobs were requeued
     */
    public static function recover_stalled_jobs(int $stalledafter): int {
        $recovered = 0;
        foreach (repository::get_stalled_jobs($stalledafter) as $stalledjob) {
            if (repository::count_pending_job_backups($stalledjob->id) > 0) {
                self::queue_backup_task($stalledjob->id, $stalledjob->userid);
            } else {
                self::queue_restore_task($stalledjob->id, $stalledjob->userid);
            }
            $recovered++;
        }
        return $recovered;
    }

    /**
     * Processes up to BACKUP_CHUNK_SIZE pending backups for a job, requeuing itself until none remain.
     *
     * @param int $jobid
     * @return void
     */
    public static function process_backups(int $jobid): void {
        $job = repository::get_job($jobid);
        if (!$job || $job->is_terminal()) {
            return;
        }

        if (!self::has_source_capability($job)) {
            self::mark_job_failed($jobid, get_string('errorsourcecapabilitylost', 'block_activity_copy_cart'));
            return;
        }

        repository::update_job($jobid, ['status' => job::STATUS_RUNNING]);

        $cart = json_decode($job->cart, true);
        $chunk = repository::get_pending_job_backups($jobid, self::BACKUP_CHUNK_SIZE);

        foreach ($chunk as $backuprow) {
            self::process_one_backup($backuprow, $cart['items'][$backuprow->sourcecmid] ?? null, $job->userid);
        }

        if (repository::count_pending_job_backups($jobid) > 0) {
            self::queue_backup_task($jobid, $job->userid);
            return;
        }

        self::queue_restore_task($jobid, $job->userid);
    }

    /**
     * Processes up to CHUNK_SIZE pending (item, target course) restores for a job, requeuing until done.
     *
     * @param int $jobid
     * @return void
     */
    public static function process_restores(int $jobid): void {
        $job = repository::get_job($jobid);
        if (!$job || $job->is_terminal()) {
            return;
        }

        if (!self::has_source_capability($job)) {
            self::mark_job_failed($jobid, get_string('errorsourcecapabilitylost', 'block_activity_copy_cart'));
            return;
        }

        $cart = json_decode($job->cart, true);
        $targetcourseids = json_decode($job->targetcourseids, true);
        $backups = repository::get_job_backups_by_cmid($jobid);
        $processed = repository::get_processed_pairs($jobid);

        $chunk = array_slice(self::pending_pairs($cart['items'], $targetcourseids, $processed), 0, self::CHUNK_SIZE);
        foreach ($chunk as [$item, $targetcourseid]) {
            self::process_pair($jobid, $job->userid, $item, $targetcourseid, $backups[$item['cmid']] ?? null);
        }

        $completed = repository::count_results($jobid);
        repository::update_job($jobid, ['completedunits' => $completed]);

        self::cleanup_consumed_backups($jobid, $backups, count($targetcourseids));

        if ($completed < $job->totalunits) {
            self::queue_restore_task($jobid, $job->userid);
            return;
        }

        $finalstatus = repository::has_incomplete_results($jobid) ? job::STATUS_COMPLETED_WITH_ERRORS : job::STATUS_COMPLETED;
        repository::update_job($jobid, ['status' => $finalstatus]);
        self::notify_owner($jobid, $job->userid, $completed, $job->totalunits, $finalstatus);
    }

    /**
     * Marks a job as failed and notifies its owner.
     *
     * @param int $jobid
     * @param string $message Why the job (not just one unit) stopped
     * @return void
     */
    public static function mark_job_failed(int $jobid, string $message): void {
        $job = repository::get_job($jobid);
        repository::update_job($jobid, ['status' => job::STATUS_FAILED, 'failuremessage' => $message]);
        if ($job) {
            self::notify_owner($jobid, $job->userid, $job->completedunits, $job->totalunits, job::STATUS_FAILED);
        }
    }

    /**
     * Backs up a single cart item, recording the outcome on its job_backup row.
     *
     * @param \stdClass $backuprow The pending job_backup row
     * @param array|null $item The matching cart item, or null if it's gone missing from the cart snapshot
     * @param int $userid
     * @return void
     */
    private static function process_one_backup(\stdClass $backuprow, ?array $item, int $userid): void {
        if ($item === null) {
            repository::update_job_backup($backuprow->id, [
                'status' => 'failed',
                'message' => get_string('errorbackupfailed', 'block_activity_copy_cart'),
            ]);
            return;
        }

        try {
            $backupid = backup::create($item, $userid);
            repository::update_job_backup($backuprow->id, ['status' => 'done', 'backupid' => $backupid]);
        } catch (\Throwable $e) {
            repository::update_job_backup($backuprow->id, ['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Restores a single (item, target course) pair, recording the outcome as a job_result row.
     *
     * @param int $jobid
     * @param int $userid
     * @param array $item
     * @param int $targetcourseid
     * @param \stdClass|null $backuprow This item's job_backup row, or null if it's missing/not done
     * @return void
     */
    private static function process_pair(int $jobid, int $userid, array $item, int $targetcourseid, ?\stdClass $backuprow): void {
        if ($backuprow === null || $backuprow->status !== 'done') {
            $default = get_string('errorbackupfailed', 'block_activity_copy_cart');
            repository::add_result(
                $jobid,
                $item['cmid'],
                $targetcourseid,
                null,
                'failed',
                $backuprow !== null ? ($backuprow->message ?? $default) : $default
            );
            return;
        }

        if (!has_capability('moodle/restore:restoretargetimport', \context_course::instance($targetcourseid), $userid)) {
            repository::add_result(
                $jobid,
                $item['cmid'],
                $targetcourseid,
                null,
                'skipped',
                get_string('errortargetcapabilitylost', 'block_activity_copy_cart')
            );
            return;
        }

        $result = restore::into_course($backuprow->backupid, $item, $targetcourseid, $userid);
        repository::add_result($jobid, $item['cmid'], $targetcourseid, $result['newcmid'], $result['status'], $result['message']);
    }

    /**
     * Builds the list of (item, target course) pairs not yet in the processed set.
     *
     * @param array $items
     * @param array $targetcourseids
     * @param array $processed As returned by \block_activity_copy_cart\app\copy\repository::get_processed_pairs()
     * @return array List of [item, targetcourseid] tuples
     */
    private static function pending_pairs(array $items, array $targetcourseids, array $processed): array {
        // Recomputed in full on every chunk execution rather than being a DB-driven "next N"
        // query - acceptable now that create_job() rejects anything over MAX_TOTAL_UNITS, which
        // bounds count($items) * count($targetcourseids) for the lifetime of any given job.
        $pairs = [];
        foreach ($items as $item) {
            foreach ($targetcourseids as $targetcourseid) {
                $targetcourseid = (int) $targetcourseid;
                if (isset($processed["{$item['cmid']}-{$targetcourseid}"])) {
                    continue;
                }
                $pairs[] = [$item, $targetcourseid];
            }
        }
        return $pairs;
    }

    /**
     * Deletes a backup's temp files once every target course has consumed it.
     *
     * @param int $jobid
     * @param array $backups Keyed by sourcecmid, as from repository::get_job_backups_by_cmid()
     * @param int $targetcourseidcount
     * @return void
     */
    private static function cleanup_consumed_backups(int $jobid, array $backups, int $targetcourseidcount): void {
        if (empty($backups)) {
            return;
        }

        // One grouped query instead of one COUNT per cart item, every chunk execution.
        $resultcounts = repository::count_results_by_cmid($jobid);

        foreach ($backups as $sourcecmid => $backuprow) {
            if ($backuprow->status !== 'done' || $backuprow->timecleaned !== null) {
                continue;
            }
            if (($resultcounts[$sourcecmid] ?? 0) < $targetcourseidcount) {
                continue;
            }
            \backup_helper::delete_backup_dir($backuprow->backupid);
            repository::update_job_backup($backuprow->id, ['timecleaned' => time()]);
        }
    }

    /**
     * Queues a backup_task for a job.
     *
     * @param int $jobid
     * @param int $userid
     * @return void
     */
    private static function queue_backup_task(int $jobid, int $userid): void {
        self::queue_task(\block_activity_copy_cart\task\backup_task::class, $jobid, $userid);
    }

    /**
     * Queues a restore_task for a job.
     *
     * @param int $jobid
     * @param int $userid
     * @return void
     */
    private static function queue_restore_task(int $jobid, int $userid): void {
        self::queue_task(\block_activity_copy_cart\task\restore_task::class, $jobid, $userid);
    }

    /**
     * Queues an adhoc task to run as a specific user, rather than cron's default identity.
     *
     * @param string $taskclass
     * @param int $jobid
     * @param int $userid
     * @return void
     */
    private static function queue_task(string $taskclass, int $jobid, int $userid): void {
        $task = new $taskclass();
        $task->set_custom_data(['jobid' => $jobid]);
        // The core fix vs admin/tool/bulkactivity's own task: without this,
        // the task runs as cron's default user, not the teacher who started
        // the job, and every capability re-check above would silently check
        // the wrong identity. See lib/classes/cron.php's run_inner_adhoc_task().
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Sends the job owner a notification summarising how a job finished.
     *
     * @param int $jobid
     * @param int $userid
     * @param int $completedunits
     * @param int $totalunits
     * @param string $status One of job::STATUS_*
     * @return void
     */
    private static function notify_owner(int $jobid, int $userid, int $completedunits, int $totalunits, string $status): void {
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $a = (object) [
            'completedunits' => $completedunits,
            'totalunits' => $totalunits,
            'status' => get_string('status' . str_replace('_', '', $status), 'block_activity_copy_cart'),
        ];

        $message = new \core\message\message();
        $message->component = 'block_activity_copy_cart';
        $message->name = 'copycompleted';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('copycompletedmessagesubject', 'block_activity_copy_cart');
        $message->fullmessage = get_string('copycompletedmessagebody', 'block_activity_copy_cart', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/blocks/activity_copy_cart/copy_progress.php', ['jobid' => $jobid]))->out(false);
        $message->contexturlname = get_string('copyprogresstitle', 'block_activity_copy_cart');
        message_send($message);
    }

    /**
     * Localizes one of job::STATUS_* for display.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        return get_string('status' . str_replace('_', '', $status), 'block_activity_copy_cart');
    }

    /**
     * Formats a set of target courses for display (id + formatted fullname).
     *
     * @param array $courseids
     * @return array
     */
    public static function target_courses(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        $records = $DB->get_records_list('course', 'id', $courseids, 'fullname', 'id, fullname');
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => $record->id,
                'fullname' => format_string($record->fullname, true, ['context' => \context_course::instance($record->id)]),
            ];
        }
        return $rows;
    }

    /**
     * Exports a job's status/progress for the progress page and its polling endpoint.
     *
     * @param job $job
     * @return array
     */
    public static function job_context(job $job): array {
        $percent = $job->totalunits > 0 ? (int) round(($job->completedunits / $job->totalunits) * 100) : 0;

        return [
            'jobid' => $job->id,
            'status' => $job->status,
            'statuslabel' => self::status_label($job->status),
            'completedunits' => $job->completedunits,
            'totalunits' => $job->totalunits,
            'percent' => $percent,
            'isterminal' => $job->is_terminal(),
        ];
    }

    /**
     * Formats a job's per-unit results for display, joining in the activity name and target course fullname.
     *
     * @param job $job
     * @param array $results As returned by \block_activity_copy_cart\app\copy\repository::get_results()
     * @return array
     */
    public static function result_rows(job $job, array $results): array {
        global $DB;

        if (empty($results)) {
            return [];
        }

        $names = [];
        foreach (json_decode($job->cart, true)['items'] as $item) {
            $names[$item['cmid']] = $item['name'];
        }

        $courseids = array_values(array_unique(array_map(
            fn(\stdClass $result): int => (int) $result->targetcourseid,
            $results
        )));
        $courses = $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname');

        $rows = [];
        foreach ($results as $result) {
            $course = $courses[$result->targetcourseid] ?? null;
            $rows[] = [
                'activityname' => $names[$result->sourcecmid] ?? '',
                'coursefullname' => $course
                    ? format_string($course->fullname, true, ['context' => \context_course::instance($course->id)])
                    : '',
                'statuslabel' => get_string('result' . $result->status, 'block_activity_copy_cart'),
                'message' => $result->message ?? '',
            ];
        }
        return $rows;
    }
}
