<?php

namespace block_activity_copy_cart\task;

use block_activity_copy_cart\app\copy\manager;


/**
 * Recovers copy jobs left stuck in a non-terminal status by an adhoc task that died without
 * a catchable \Throwable (PHP fatal error, max_execution_time timeout, OOM, server restart) -
 * without this, such a job never progresses again and its owner's progress page polls forever.
 */
class recover_stalled_jobs_task extends \core\task\scheduled_task {
    /** @var int Seconds of inactivity before a non-terminal job counts as stalled. */
    private const STALLED_AFTER_SECONDS = 3 * HOURSECS;

    public function get_name(): string {
        return get_string('task_recoverstalledjobs', 'block_activity_copy_cart');
    }

    public function execute(): void {
        $recovered = manager::recover_stalled_jobs(self::STALLED_AFTER_SECONDS);
        if ($recovered > 0) {
            mtrace("block_activity_copy_cart: requeued {$recovered} stalled job(s).");
        }
    }
}
