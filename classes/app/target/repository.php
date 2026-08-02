<?php

namespace block_activity_copy_cart\app\target;


final class repository {
    const SESSION_KEY = 'block_activity_copy_cart_target_courses';

    public static function save(int $sourcecourseid, array $courseids, array $categoryids): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = [
            'sourcecourseid' => $sourcecourseid,
            'courseids' => array_values(array_unique(array_map('intval', $courseids))),
            'categoryids' => array_values(array_unique(array_map('intval', $categoryids))),
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
