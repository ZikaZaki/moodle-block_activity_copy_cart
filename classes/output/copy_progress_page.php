<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\copy\job;
use block_activity_copy_cart\app\copy\manager;
use block_activity_copy_cart\app\copy\repository;


final class copy_progress_page implements \core\output\named_templatable, \renderable {

    public function __construct(
        private job $job,
        private \moodle_url $courseurl,
    ) {
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'block_activity_copy_cart/copy/content';
    }

    public function export_for_template(\renderer_base $output): array {
        $context = manager::job_context($this->job);
        $context['results'] = manager::result_rows($this->job, repository::get_results($this->job->id));
        $context['courseurl'] = $this->courseurl->out(false);
        return $context;
    }
}
