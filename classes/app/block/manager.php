<?php

namespace block_activity_copy_cart\app\block;

use block_activity_copy_cart\exception\exception;


/**
 * Builds, formats, and hydrates the drag-and-drop cart from submitted/stored data.
 */
final class manager {
    /**
     * Resolves and returns the single course every given cmid belongs to - the cheap first step
     * of build(), extracted so callers can authorize against the real source course before paying
     * for build()'s more expensive per-item hydration (get_fast_modinfo(), name/icon formatting).
     *
     * @param array $cmids
     * @return int
     * @throws exception If the cmids don't all belong to exactly one course
     */
    public static function resolve_source_course(array $cmids): int {
        global $DB;

        if (empty($cmids)) {
            throw new exception('cartempty');
        }

        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $courseids = array_unique($DB->get_fieldset_select('course_modules', 'course', "id $insql", $inparams));
        if (count($courseids) !== 1) {
            throw new exception('cartinvalid');
        }
        return (int) reset($courseids);
    }

    /**
     * Builds a cart directly from the current request's cmids[]/rename[]/etc. arrays.
     *
     * @return array
     */
    public static function from_submitted_data(): array {
        $cmids = optional_param_array('cmids', [], PARAM_INT);
        $cmids = array_values(array_unique(array_filter($cmids)));

        $renames = optional_param_array('rename', [], PARAM_TEXT);
        $sectionmatch = optional_param_array('sectionmatch', [], PARAM_ALPHA);
        $section = optional_param_array('section', [], PARAM_INT);
        $sectionname = optional_param_array('sectionname', [], PARAM_TEXT);
        $sectionmissing = optional_param_array('sectionmissing', [], PARAM_ALPHA);
        $nameconflict = optional_param_array('nameconflict', [], PARAM_ALPHA);
        $visibility = optional_param_array('visibility', [], PARAM_ALPHA);
        $restrictions = optional_param_array('restrictions', [], PARAM_INT);

        $rawitems = [];
        foreach ($cmids as $cmid) {
            $key = (string) $cmid;
            $rawitems[$key] = [
                'rename' => $renames[$key] ?? '',
                'sectionmatch' => $sectionmatch[$key] ?? null,
                'section' => $section[$key] ?? null,
                'sectionname' => $sectionname[$key] ?? null,
                'sectionmissing' => $sectionmissing[$key] ?? null,
                'nameconflict' => $nameconflict[$key] ?? null,
                'visibility' => $visibility[$key] ?? null,
                'restrictions' => $restrictions[$key] ?? null,
            ];
        }

        return self::build($cmids, $rawitems);
    }

    /**
     * Builds a cart from a set of cmids and their (optional) per-item raw settings.
     *
     * @param array $cmids
     * @param array $rawitems Per-cmid raw settings, keyed by cmid as a string; see from_submitted_data()
     * @return array{sourcecourseid: int, items: array}
     */
    public static function build(array $cmids, array $rawitems): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        if (empty($cmids)) {
            throw new exception('cartempty');
        }

        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $coursemodules = $DB->get_records_select('course_modules', "id $insql", $inparams, '', 'id, course');

        $courseids = array_unique(array_column($coursemodules, 'course'));
        if (count($courseids) !== 1) {
            throw new exception('cartinvalid');
        }
        $sourcecourseid = (int) reset($courseids);

        $modinfo = get_fast_modinfo($sourcecourseid);
        $course = $modinfo->get_course();

        $items = [];
        foreach ($cmids as $cmid) {
            if (empty($coursemodules[$cmid])) {
                continue;
            }
            try {
                $cminfo = $modinfo->get_cm($cmid);
            } catch (\Exception $ignored) {
                continue;
            }

            $key = (string) $cmid;
            $raw = $rawitems[$key] ?? [];
            $ownsection = (int) $cminfo->sectionnum;

            $items[$cmid] = [
                'cmid' => $cmid,
                'modname' => $cminfo->modname,
                'name' => $cminfo->get_formatted_name(),
                'contextid' => $cminfo->context->id,
                'rename' => trim($raw['rename'] ?? ''),
                'sectionmatch' => item_settings::sanitize('sectionmatch', $raw['sectionmatch'] ?? null),
                // The save_cart AJAX endpoint's schema fills in a default of -1 (never a real
                // section number) for an omitted section, unlike the direct POST path where a
                // truly-absent key is null - checking >= 0 treats both the same way, rather than
                // isset() alone silently accepting the AJAX default as if it were explicit.
                'section' => (isset($raw['section']) && (int) $raw['section'] >= 0) ? (int) $raw['section'] : $ownsection,
                'sectionname' => $raw['sectionname'] ?? get_section_name($course, $ownsection),
                'sectionmissing' => item_settings::sanitize('sectionmissing', $raw['sectionmissing'] ?? null),
                'nameconflict' => item_settings::sanitize('nameconflict', $raw['nameconflict'] ?? null),
                'visibility' => item_settings::sanitize('visibility', $raw['visibility'] ?? null),
                'restrictions' => (bool) ($raw['restrictions'] ?? item_settings::DEFAULTS['restrictions']),
            ];
        }

        if (empty($items)) {
            throw new exception('cartempty');
        }

        return [
            'sourcecourseid' => $sourcecourseid,
            'items' => $items,
        ];
    }

    /**
     * Formats cart items that still exist for the block's own cart display.
     *
     * @param array $cart
     * @return array
     */
    public static function item_rows(array $cart): array {
        $modinfo = get_fast_modinfo($cart['sourcecourseid']);
        $rows = [];
        foreach (self::existing_items($cart, $modinfo) as $item) {
            $name = self::display_name($item);
            $rows[] = [
                'cmid' => $item['cmid'],
                'cmname' => $name,
                'iconurl' => self::icon_url($modinfo, $item),
                'movetitle' => get_string('movecontent', 'moodle', $name),
            ];
        }
        return $rows;
    }

    /**
     * Renders every still-existing cart item's fields as hidden form inputs, for the copy wizard's form post.
     *
     * @param array $cart
     * @return string
     */
    public static function hidden_fields_html(array $cart): string {
        $modinfo = get_fast_modinfo($cart['sourcecourseid']);
        $html = '';
        foreach (self::existing_items($cart, $modinfo) as $item) {
            $cmid = $item['cmid'];
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmids[]', 'value' => $cmid]);
            $html .= self::hidden_field($cmid, 'rename', $item['rename']);
            $html .= self::hidden_field($cmid, 'sectionmatch', $item['sectionmatch']);
            $html .= self::hidden_field($cmid, 'section', (string) $item['section']);
            $html .= self::hidden_field($cmid, 'sectionname', $item['sectionname']);
            $html .= self::hidden_field($cmid, 'sectionmissing', $item['sectionmissing']);
            $html .= self::hidden_field($cmid, 'nameconflict', $item['nameconflict']);
            $html .= self::hidden_field($cmid, 'visibility', $item['visibility']);
            $html .= self::hidden_field($cmid, 'restrictions', $item['restrictions'] ? '1' : '0');
        }
        return $html;
    }

    /**
     * Filters a cart down to items whose activity still exists in the source course.
     *
     * @param array $cart
     * @param \course_modinfo $modinfo
     * @return array
     */
    private static function existing_items(array $cart, \course_modinfo $modinfo): array {
        return array_filter($cart['items'], function (array $item) use ($modinfo): bool {
            try {
                $modinfo->get_cm($item['cmid']);
                return true;
            } catch (\Exception $ignored) {
                return false;
            }
        });
    }

    /**
     * Builds one hidden input for one cart item's field.
     *
     * @param int $cmid
     * @param string $key
     * @param string $value
     * @return string
     */
    private static function hidden_field(int $cmid, string $key, string $value): string {
        return \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'id' => "{$key}-hidden-{$cmid}",
            'name' => "{$key}[{$cmid}]",
            'value' => $value,
        ]);
    }

    /**
     * Formats every cart item for the target-course-selection review page.
     *
     * @param array $cart
     * @return array
     */
    public static function rows(array $cart): array {
        $modinfo = get_fast_modinfo($cart['sourcecourseid']);
        $rows = [];
        foreach ($cart['items'] as $item) {
            $rows[] = [
                'name' => self::display_name($item),
                'iconurl' => self::icon_url($modinfo, $item),
                // Left for the template's auto-escaping: see the class docblock.
                'sectionname' => $item['sectionname'],
                'badges' => self::badges($item),
            ];
        }
        return $rows;
    }

    /**
     * The name a cart item's copy will display as: its rename if set, otherwise the source activity's own name.
     *
     * @param array $item
     * @return string
     */
    public static function display_name(array $item): string {
        if ($item['rename'] === '') {
            return $item['name'];
        }
        return format_string($item['rename'], true, ['context' => \context::instance_by_id($item['contextid'])]);
    }

    /**
     * A cart item's icon URL, falling back to its module type's generic icon if the activity's own is unavailable.
     *
     * @param \course_modinfo $modinfo
     * @param array $item
     * @return string
     */
    public static function icon_url(\course_modinfo $modinfo, array $item): string {
        global $OUTPUT;

        try {
            return $modinfo->get_cm($item['cmid'])->get_icon_url()->out(false);
        } catch (\moodle_exception $ignored) {
            return $OUTPUT->image_url('monologo', $item['modname'])->out(false);
        }
    }

    /**
     * Lists the non-default settings worth surfacing as badges on a cart item (rename, visibility, user data).
     *
     * @param array $item
     * @return array
     */
    private static function badges(array $item): array {
        $badges = [];
        if ($item['rename'] !== '') {
            $badges[] = get_string('badgerenamed', 'block_activity_copy_cart', $item['rename']);
        }
        if ($item['visibility'] !== item_settings::VISIBILITY_SOURCE) {
            $badges[] = get_string('visibility' . $item['visibility'], 'block_activity_copy_cart');
        }
        return $badges;
    }
}
