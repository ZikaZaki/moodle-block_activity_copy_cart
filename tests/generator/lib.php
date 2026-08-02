<?php


class block_activity_copy_cart_generator extends component_generator_base {

    public function create_job(array $record = []): int {
        global $USER;

        $userid = (int) ($record['userid'] ?? $USER->id);
        $sourcecourseid = (int) ($record['sourcecourseid'] ?? 0);
        $cart = $record['cart'] ?? ['sourcecourseid' => $sourcecourseid, 'items' => []];
        $targetcourseids = $record['targetcourseids'] ?? [];

        return \block_activity_copy_cart\app\copy\repository::create_job($userid, $sourcecourseid, $cart, $targetcourseids);
    }
}
