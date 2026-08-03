<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Code that is executed before the tables and data are dropped during the plugin uninstallation.
 *
 * @package     block_activity_copy_cart
 * @category    upgrade
 * @author      ZikaZaki <zika.github@gmail.com>
 * @copyright   2026 Numo <https://numo.sa>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom uninstallation procedure.
 */
function xmldb_block_activity_copy_cart_uninstall() {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

    // Jobs stuck pending/running at uninstall time can have backups that were created but
    // never consumed/cleaned up (manager::cleanup_consumed_backups() only ever runs as part
    // of a job actually finishing) - without this, those temp directories become permanently
    // orphaned once this plugin's own tables (which track their backupids) are dropped.
    $backups = $DB->get_records_select(
        'block_activity_copy_cart_bkp',
        'backupid IS NOT NULL AND timecleaned IS NULL'
    );
    foreach ($backups as $backup) {
        try {
            \backup_helper::delete_backup_dir($backup->backupid);
        } catch (\Throwable $e) {
            debugging(
                'block_activity_copy_cart: failed to clean up backup dir for backupid ' .
                $backup->backupid . ' during uninstall: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    return true;
}
