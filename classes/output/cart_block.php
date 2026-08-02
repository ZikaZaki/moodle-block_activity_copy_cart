<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\block\manager;
use block_activity_copy_cart\app\block\repository;


final class cart_block implements \core\output\named_templatable, \renderable {

    public function __construct(
        private int $courseid,
    ) {
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'block_activity_copy_cart/block/content';
    }

    public function export_for_template(\renderer_base $output): array {
        $targeturl = new \moodle_url('/blocks/activity_copy_cart/target_courses.php');
        $cart = repository::get();
        $cartitemshtml = '';
        $hiddenfieldshtml = '';
        if ($cart && (int) $cart['sourcecourseid'] === $this->courseid) {
            foreach (manager::item_rows($cart) as $row) {
                $cartitemshtml .= $output->render_from_template('block_activity_copy_cart/block/item', $row);
            }
            $hiddenfieldshtml = manager::hidden_fields_html($cart);
        }

        return [
            'copyurl' => $targeturl->out(false),
            'sesskey' => sesskey(),
            'courseid' => $this->courseid,
            'cartemptytext' => get_string('cartempty', 'block_activity_copy_cart'),
            'clearcartbtntext' => get_string('clearcart', 'block_activity_copy_cart'),
            'copyactivitiesbtntext' => get_string('copyactivities', 'block_activity_copy_cart'),
            'cartitemshtml' => $cartitemshtml,
            'hiddenfieldshtml' => $hiddenfieldshtml,
        ];
    }
}
