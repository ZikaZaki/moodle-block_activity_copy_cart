<?php

namespace block_activity_copy_cart\app\copy;

use block_activity_copy_cart\exception\exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


/**
 * Backs up a single activity ahead of being restored into every target course.
 */
final class backup {
    /**
     * Backs up one cart item's activity, returning the backup id for restore::into_course() to reuse.
     *
     * @param array $item One cart item, as built by \block_activity_copy_cart\app\block\manager::build()
     * @param int $userid The user the backup runs as
     * @return string The backup id
     */
    public static function create(array $item, int $userid): string {
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $item['cmid'],
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            // Always MODE_IMPORT. Moodle's own backup_check::check_security() forces
            // `users=false` and locks it whenever mode is MODE_IMPORT, and a MODE_GENERAL
            // backup's temp directory is deleted before restore.php could ever reuse it
            // (see the shared temp-dir architecture in restore::restore_backup()) - so
            // this plugin cannot support carrying user data over with the current design.
            \backup::MODE_IMPORT,
            $userid
        );

        try {
            $backupid = $bc->get_backupid();
            $bc->execute_plan();

            if ($bc->get_status() !== \backup::STATUS_FINISHED_OK) {
                throw new exception('errorbackupfailed');
            }
        } finally {
            $bc->destroy();
        }

        return $backupid;
    }
}
