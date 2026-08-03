<?php

namespace block_activity_copy_cart\app\target;


final class repository {
    const SESSION_KEY = 'block_activity_copy_cart_target_courses';

    /**
     * @var int Defense-in-depth backstop matching external\save_target_courses::MAX_IDS - that
     *  endpoint already rejects an oversized submission with a clear error before it ever reaches
     *  here, but this bounds session storage regardless of caller.
     */
    private const MAX_IDS = 1000;

    public static function save(int $sourcecourseid, array $courseids, array $categoryids): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = [
            'sourcecourseid' => $sourcecourseid,
            'courseids' => array_slice(array_values(array_unique(array_map('intval', $courseids))), 0, self::MAX_IDS),
            'categoryids' => array_slice(array_values(array_unique(array_map('intval', $categoryids))), 0, self::MAX_IDS),
        ];
    }

    public static function get(int $sourcecourseid): array {
        global $SESSION;
        $stored = $SESSION->{self::SESSION_KEY} ?? null;

        if (!$stored || (int) ($stored['sourcecourseid'] ?? 0) !== $sourcecourseid) {
            return ['courseids' => [], 'categoryids' => []];
        }

        return [
            'courseids' => $stored['courseids'],
            'categoryids' => $stored['categoryids'],
        ];
    }

    public static function clear(): void {
        global $SESSION;
        unset($SESSION->{self::SESSION_KEY});
    }
}
