<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\block\manager;


final class target_courses_page implements \core\output\named_templatable, \renderable {

    public function __construct(
        private \moodle_url $courseurl,
        private \moodle_url $pageurl,
        private bool $reviewing,
        private array $cart,
        private array $targetcourserows,
        private string $formhtml,
    ) {
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'block_activity_copy_cart/target/content';
    }

    public function export_for_template(\renderer_base $output): array {
        return [
            'courseurl' => $this->courseurl->out(false),
            'pageurl' => $this->pageurl->out(false),
            'sesskey' => sesskey(),
            'reviewing' => $this->reviewing,
            'cartitems' => manager::rows($this->cart),
            'cartcount' => count($this->cart['items']),
            'targetcourses' => $this->targetcourserows,
            'targetcount' => count($this->targetcourserows),
            'formhtml' => $this->formhtml,
        ];
    }
}
