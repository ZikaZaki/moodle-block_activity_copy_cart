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
 * Upgrade steps for block_activity_copy_cart.
 *
 * @package     block_activity_copy_cart
 * @author      ZikaZaki <zika.github@gmail.com>
 * @copyright   2026 Numo <https://numo.sa>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the plugin's upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_activity_copy_cart_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026073100) {
        $table = new xmldb_table('block_activity_copy_cart_job');
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'pending', 'targetcourseids');

        // status was originally char(20), too short to hold 'completed_with_errors' (21 chars).
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        upgrade_block_savepoint(true, 2026073100, 'activity_copy_cart');
    }

    if ($oldversion < 2026080301) {
        $table = new xmldb_table('block_activity_copy_cart_res');
        $index = new xmldb_index('jobid_cmid_idx', XMLDB_INDEX_NOTUNIQUE, ['jobid', 'sourcecmid']);

        // Supports count_results_by_cmid()'s per-cmid grouping, called once per restore chunk.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026080301, 'activity_copy_cart');
    }

    return true;
}
