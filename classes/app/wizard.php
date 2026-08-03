<?php

namespace block_activity_copy_cart\app;

use block_activity_copy_cart\app\block\repository as cart_repository;
use block_activity_copy_cart\app\target\repository as target_repository;


/**
 * Clears the copy wizard's session state - the cart and the target-course selection - shared
 * across target_courses.php's cancel/is_cancelled paths.
 */
final class wizard {
    /**
     * Clears the wizard's session state and redirects back to the source course.
     *
     * @param \moodle_url $courseurl
     * @return void
     */
    public static function cancel(\moodle_url $courseurl): void {
        cart_repository::clear();
        target_repository::clear();
        redirect($courseurl);
    }
}
