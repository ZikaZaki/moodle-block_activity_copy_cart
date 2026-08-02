<?php

namespace block_activity_copy_cart\task;

use block_activity_copy_cart\app\copy\manager;


class backup_task extends \core\task\adhoc_task {

    public function execute(): void {
        $jobid = (int) $this->get_custom_data()->jobid;

        try {
            manager::process_backups($jobid);
        } catch (\Throwable $e) {
            mtrace('block_activity_copy_cart backup_task failed for job ' . $jobid . ': ' . $e->getMessage());
            manager::mark_job_failed($jobid, $e->getMessage());
        }
    }

    public function retry_until_success(): bool {
        return false;
    }

    public function get_name(): string {
        return parent::get_name() . ' (block_activity_copy_cart)';
    }
}
