<?php

namespace block_activity_copy_cart\exception;


class exception extends \moodle_exception {

    public function __construct(string $errorcode, $a = null, string $link = '', ?string $debuginfo = null) {
        parent::__construct($errorcode, 'block_activity_copy_cart', $link, $a, $debuginfo);
    }
}
