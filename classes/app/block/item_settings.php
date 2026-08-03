<?php

namespace block_activity_copy_cart\app\block;


/**
 * Per-cart-item settings: allowed values, defaults, and sanitization.
 */
final class item_settings {
    const SECTION_MATCH_NAME = 'name';

    /** @var string Match a target section by its position (number). */
    const SECTION_MATCH_POSITION = 'position';

    /** @var string Skip the activity if its target section doesn't exist. */
    const SECTION_MISSING_SKIP = 'skip';

    /** @var string Create the missing target section. */
    const SECTION_MISSING_CREATE = 'create';

    /** @var string Skip the activity if its name conflicts in the target section. */
    const NAME_CONFLICT_SKIP = 'skip';

    /** @var string Auto-rename the activity to resolve the conflict. */
    const NAME_CONFLICT_RESOLVE = 'resolve';

    /** @var string Keep the same visibility as the source activity. */
    const VISIBILITY_SOURCE = 'source';

    /** @var string Force the copied activity to be shown. */
    const VISIBILITY_SHOW = 'show';

    /** @var string Force the copied activity to be hidden. */
    const VISIBILITY_HIDE = 'hide';

    /**
     * Allowed values per enum-valued settings field, keyed the same as the
     * cart item array built by block\manager::from_submitted_data(). If a
     * submitted value isn't in the allowed list, the default for that field
     * is used instead.
     *
     * @var array<string, string[]>
     */
    const ALLOWED_VALUES = [
        'sectionmatch' => [self::SECTION_MATCH_NAME, self::SECTION_MATCH_POSITION],
        'sectionmissing' => [self::SECTION_MISSING_SKIP, self::SECTION_MISSING_CREATE],
        'nameconflict' => [self::NAME_CONFLICT_SKIP, self::NAME_CONFLICT_RESOLVE],
        'visibility' => [self::VISIBILITY_SOURCE, self::VISIBILITY_SHOW, self::VISIBILITY_HIDE],
    ];

    /**
     * Default values for every keyed per-activity settings field. An item
     * only has a submitted value at all once its settings modal has been
     * saved once, so these defaults apply whenever that never happened.
     *
     * @var array<string, string>
     */
    const DEFAULTS = [
        'sectionmatch' => self::SECTION_MATCH_NAME,
        'sectionmissing' => self::SECTION_MISSING_CREATE,
        'nameconflict' => self::NAME_CONFLICT_RESOLVE,
        'visibility' => self::VISIBILITY_SOURCE,
        'restrictions' => '1',
    ];

    /**
     * Validates a submitted value for one of the enum-valued fields,
     * falling back to that field's default when the value is missing or
     * isn't one of the allowed options.
     *
     * @param string $field One of the keys in ALLOWED_VALUES
     * @param string|null $value The submitted value, if any
     * @return string A value guaranteed to be in ALLOWED_VALUES[$field]
     */
    public static function sanitize(string $field, ?string $value): string {
        if (!isset(self::ALLOWED_VALUES[$field])) {
            throw new \coding_exception("fields::sanitize() called for unknown field '{$field}'");
        }
        if ($value !== null && in_array($value, self::ALLOWED_VALUES[$field], true)) {
            return $value;
        }
        return self::DEFAULTS[$field];
    }
}
