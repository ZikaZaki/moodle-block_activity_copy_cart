<?php

namespace block_activity_copy_cart\traits;


trait course_authorization {

    protected static function authorize_course(int $courseid): \context_course {
        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('block/activity_copy_cart:copyactivities', $context);
        return $context;
    }
}
