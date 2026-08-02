<?php

namespace block_activity_copy_cart\app\copy;


final class repository {

    public static function create_job(int $userid, int $sourcecourseid, array $cart, array $targetcourseids): int {
        global $DB;

        $now = time();
        $totalunits = count($cart['items']) * count($targetcourseids);

        return $DB->insert_record('block_activity_copy_cart_job', (object) [
            'userid' => $userid,
            'sourcecourseid' => $sourcecourseid,
            'cart' => json_encode($cart),
            'targetcourseids' => json_encode(array_values($targetcourseids)),
            'status' => job::STATUS_PENDING,
            'totalunits' => $totalunits,
            'completedunits' => 0,
            'failuremessage' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    public static function get_job(int $jobid): ?job {
        global $DB;
        $record = $DB->get_record('block_activity_copy_cart_job', ['id' => $jobid]);
        return $record ? job::from_record($record) : null;
    }

    public static function update_job(int $jobid, array $fields): void {
        global $DB;
        $fields['id'] = $jobid;
        $fields['timemodified'] = time();
        $DB->update_record('block_activity_copy_cart_job', (object) $fields);
    }

    public static function create_job_backups(int $jobid, array $sourcecmids): void {
        global $DB;

        $now = time();
        foreach (array_unique($sourcecmids) as $cmid) {
            $DB->insert_record('block_activity_copy_cart_bkp', (object) [
                'jobid' => $jobid,
                'sourcecmid' => $cmid,
                'backupid' => null,
                'status' => 'pending',
                'message' => null,
                'timecleaned' => null,
                'timecreated' => $now,
            ], false);
        }
    }

    public static function get_pending_job_backups(int $jobid): array {
        global $DB;
        return array_values($DB->get_records('block_activity_copy_cart_bkp', ['jobid' => $jobid, 'status' => 'pending']));
    }

    public static function count_pending_job_backups(int $jobid): int {
        global $DB;
        return $DB->count_records('block_activity_copy_cart_bkp', ['jobid' => $jobid, 'status' => 'pending']);
    }

    public static function get_job_backups_by_cmid(int $jobid): array {
        global $DB;

        $rows = $DB->get_records('block_activity_copy_cart_bkp', ['jobid' => $jobid]);
        $bycmid = [];
        foreach ($rows as $row) {
            $bycmid[$row->sourcecmid] = $row;
        }
        return $bycmid;
    }

    public static function update_job_backup(int $id, array $fields): void {
        global $DB;
        $fields['id'] = $id;
        $DB->update_record('block_activity_copy_cart_bkp', (object) $fields);
    }

    public static function get_processed_pairs(int $jobid): array {
        global $DB;

        $records = $DB->get_records('block_activity_copy_cart_res', ['jobid' => $jobid], '', 'id, sourcecmid, targetcourseid');
        $pairs = [];
        foreach ($records as $record) {
            $pairs["{$record->sourcecmid}-{$record->targetcourseid}"] = true;
        }
        return $pairs;
    }

    public static function add_result(
        int $jobid,
        int $sourcecmid,
        int $targetcourseid,
        ?int $newcmid,
        string $status,
        ?string $message
    ): void {
        global $DB;

        $DB->insert_record('block_activity_copy_cart_res', (object) [
            'jobid' => $jobid,
            'sourcecmid' => $sourcecmid,
            'targetcourseid' => $targetcourseid,
            'newcmid' => $newcmid,
            'status' => $status,
            'message' => $message,
            'timecreated' => time(),
        ]);
    }

    public static function count_results_for_cmid(int $jobid, int $sourcecmid): int {
        global $DB;
        return $DB->count_records('block_activity_copy_cart_res', ['jobid' => $jobid, 'sourcecmid' => $sourcecmid]);
    }

    public static function count_results(int $jobid): int {
        global $DB;
        return $DB->count_records('block_activity_copy_cart_res', ['jobid' => $jobid]);
    }

    public static function has_incomplete_results(int $jobid): bool {
        global $DB;
        return $DB->record_exists_select(
            'block_activity_copy_cart_res',
            'jobid = :jobid AND status <> :success',
            ['jobid' => $jobid, 'success' => 'success']
        );
    }

    public static function get_results(int $jobid): array {
        global $DB;
        return array_values($DB->get_records('block_activity_copy_cart_res', ['jobid' => $jobid], 'id DESC'));
    }

    public static function get_jobs_for_user(int $userid): array {
        global $DB;
        return array_values($DB->get_records('block_activity_copy_cart_job', ['userid' => $userid], 'timecreated DESC'));
    }

    public static function delete_job(int $jobid): void {
        global $DB;
        $DB->delete_records('block_activity_copy_cart_bkp', ['jobid' => $jobid]);
        $DB->delete_records('block_activity_copy_cart_res', ['jobid' => $jobid]);
        $DB->delete_records('block_activity_copy_cart_job', ['id' => $jobid]);
    }

    public static function delete_jobs_for_user(int $userid): void {
        foreach (self::get_jobs_for_user($userid) as $job) {
            self::delete_job($job->id);
        }
    }
}
