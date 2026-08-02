<?php

namespace block_activity_copy_cart\app\copy;

use block_activity_copy_cart\app\block\item_settings;
use block_activity_copy_cart\exception\exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');


/**
 * Restores one cart item's backup into one target course, applying its per-item settings.
 */
final class restore {
    /**
     * Restores a backup into one target course and applies the item's rename/visibility/restriction settings.
     *
     * @param string $backupid As returned by \block_activity_copy_cart\app\copy\backup::create()
     * @param array $item The cart item, as built by \block_activity_copy_cart\app\block\manager::build()
     * @param int $targetcourseid
     * @param int $userid
     * @return array{newcmid: int|null, status: string, message: string|null}
     */
    public static function into_course(string $backupid, array $item, int $targetcourseid, int $userid): array {
        try {
            $sectionnum = self::resolve_target_section($item, $targetcourseid);
            if ($sectionnum === null) {
                return self::skipped(get_string('skipsectionmissing', 'block_activity_copy_cart', $item['sectionname']));
            }

            $newcmid = self::restore_backup($backupid, $targetcourseid, $userid, (int) $item['contextid']);
            self::place_in_section($newcmid, $targetcourseid, $sectionnum);

            if (trim($item['rename']) !== '') {
                self::rename_instance($newcmid, $item['rename']);
            }

            $conflictaction = self::resolve_name_conflict($item, $newcmid, $targetcourseid, $sectionnum);
            if ($conflictaction === 'skip') {
                course_delete_module($newcmid);
                rebuild_course_cache($targetcourseid, true);
                return self::skipped(get_string('skipnameconflict', 'block_activity_copy_cart', $item['name']));
            }

            self::apply_visibility($item, $newcmid, $targetcourseid);
            self::apply_restrictions($item, $newcmid);

            rebuild_course_cache($targetcourseid, true);

            return ['newcmid' => $newcmid, 'status' => 'success', 'message' => null];
        } catch (\Throwable $e) {
            return ['newcmid' => null, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Builds a "skipped" result array.
     *
     * @param string $message Why it was skipped
     * @return array{newcmid: null, status: string, message: string}
     */
    private static function skipped(string $message): array {
        return ['newcmid' => null, 'status' => 'skipped', 'message' => $message];
    }

    /**
     * Resolves the target course section to place the copy in, matching by name or position.
     *
     * @param array $item
     * @param int $targetcourseid
     * @return int|null The section number, or null if it's missing and the item is set to skip
     */
    private static function resolve_target_section(array $item, int $targetcourseid): ?int {
        $modinfo = get_fast_modinfo($targetcourseid);

        if ($item['sectionmatch'] === item_settings::SECTION_MATCH_NAME) {
            $course = $modinfo->get_course();
            foreach ($modinfo->get_section_info_all() as $sectioninfo) {
                if (get_section_name($course, $sectioninfo) === $item['sectionname']) {
                    return (int) $sectioninfo->section;
                }
            }
            if ($item['sectionmissing'] === item_settings::SECTION_MISSING_SKIP) {
                return null;
            }
            $section = course_create_section($targetcourseid);
            course_update_section($targetcourseid, $section, ['name' => $item['sectionname']]);
            return (int) $section->section;
        }

        // Match by position (section number).
        $sectionnum = (int) $item['section'];
        if ($modinfo->get_section_info($sectionnum, IGNORE_MISSING)) {
            return $sectionnum;
        }
        if ($item['sectionmissing'] === item_settings::SECTION_MISSING_SKIP) {
            return null;
        }
        course_create_sections_if_missing($targetcourseid, $sectionnum);
        return $sectionnum;
    }

    /**
     * Runs the actual restore_controller, returning the new cmid it produced.
     *
     * @param string $backupid
     * @param int $targetcourseid
     * @param int $userid
     * @param int $sourcecontextid The source activity's context id, to identify the restored task among the plan's tasks
     * @return int The new cmid
     */
    private static function restore_backup(string $backupid, int $targetcourseid, int $userid, int $sourcecontextid): int {
        $rc = new \restore_controller(
            $backupid,
            $targetcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid,
            \backup::TARGET_CURRENT_ADDING
        );
        try {
            if ($rc->execute_precheck(true) !== true) {
                $errors = $rc->get_precheck_results()['errors'] ?? [];
                throw new exception('errorrestoreprecheck', implode(' ', $errors));
            }

            $rc->execute_plan();

            $newcmid = null;
            foreach ($rc->get_plan()->get_tasks() as $task) {
                if (is_subclass_of($task, 'restore_activity_task') && $task->get_old_contextid() == $sourcecontextid) {
                    $newcmid = (int) $task->get_moduleid();
                    break;
                }
            }
        } finally {
            $rc->destroy();
        }

        if (!$newcmid) {
            throw new exception('errorrestorefailed');
        }
        return $newcmid;
    }

    /**
     * Moves a freshly restored module into the resolved target section.
     *
     * @param int $cmid
     * @param int $targetcourseid
     * @param int $sectionnum
     * @return void
     */
    private static function place_in_section(int $cmid, int $targetcourseid, int $sectionnum): void {
        global $DB;

        $cm = get_coursemodule_from_id(null, $cmid, $targetcourseid, false, MUST_EXIST);
        $section = $DB->get_record('course_sections', ['course' => $targetcourseid, 'section' => $sectionnum], '*', MUST_EXIST);
        moveto_module($cm, $section);
        \course_modinfo::purge_course_module_cache($targetcourseid, $cmid);
        rebuild_course_cache($targetcourseid, true);
    }

    /**
     * Renames a module's own instance (e.g. the quiz/page/forum name, not the course module).
     *
     * @param int $cmid
     * @param string $newname
     * @return void
     */
    private static function rename_instance(int $cmid, string $newname): void {
        global $DB;

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
        $DB->set_field($cm->modname, 'name', $newname, ['id' => $cm->instance]);
    }

    /**
     * Fetches a module instance's own name.
     *
     * @param int $cmid
     * @return string
     */
    private static function get_instance_name(int $cmid): string {
        global $DB;

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
        return (string) $DB->get_field($cm->modname, 'name', ['id' => $cm->instance], MUST_EXIST);
    }

    /**
     * Resolves a name conflict in the target section: skip, or auto-rename with a disambiguating counter.
     *
     * @param array $item
     * @param int $newcmid
     * @param int $targetcourseid
     * @param int $sectionnum
     * @return string 'ok' or 'skip'
     */
    private static function resolve_name_conflict(array $item, int $newcmid, int $targetcourseid, int $sectionnum): string {
        $name = self::get_instance_name($newcmid);
        $othernames = self::other_names_in_section($targetcourseid, $sectionnum, $newcmid);

        if (!in_array($name, $othernames, true)) {
            return 'ok';
        }
        if ($item['nameconflict'] === item_settings::NAME_CONFLICT_SKIP) {
            return 'skip';
        }

        // Resolve: auto-rename with a disambiguating counter, unique within the section.
        global $DB;
        $cm = get_coursemodule_from_id(null, $newcmid, 0, false, MUST_EXIST);
        $counter = 1;
        do {
            $counter++;
            $candidate = "{$name} ({$counter})";
        } while (in_array($candidate, $othernames, true));
        $DB->set_field($cm->modname, 'name', $candidate, ['id' => $cm->instance]);

        return 'ok';
    }

    /**
     * Lists the names of every other activity already in a target section.
     *
     * @param int $targetcourseid
     * @param int $sectionnum
     * @param int $excludecmid The cmid to leave out (the one just restored)
     * @return array
     */
    private static function other_names_in_section(int $targetcourseid, int $sectionnum, int $excludecmid): array {
        $modinfo = get_fast_modinfo($targetcourseid);
        $sectioninfo = $modinfo->get_section_info($sectionnum, IGNORE_MISSING);
        if (!$sectioninfo || empty($sectioninfo->sequence)) {
            return [];
        }

        $names = [];
        foreach (explode(',', $sectioninfo->sequence) as $cmid) {
            $cmid = (int) $cmid;
            if ($cmid === $excludecmid) {
                continue;
            }
            $names[] = $modinfo->get_cm($cmid)->name;
        }
        return $names;
    }

    /**
     * Applies the item's visibility setting to the restored module, unless it's set to match the source.
     *
     * @param array $item
     * @param int $cmid
     * @param int $targetcourseid
     * @return void
     */
    private static function apply_visibility(array $item, int $cmid, int $targetcourseid): void {
        if ($item['visibility'] === item_settings::VISIBILITY_SOURCE) {
            return;
        }
        set_coursemodule_visible($cmid, $item['visibility'] === item_settings::VISIBILITY_SHOW ? 1 : 0);
    }

    /**
     * Clears the restored module's access restrictions, unless the item is set to keep them.
     *
     * @param array $item
     * @param int $cmid
     * @return void
     */
    private static function apply_restrictions(array $item, int $cmid): void {
        if ($item['restrictions']) {
            return;
        }
        global $DB;
        $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
    }
}
