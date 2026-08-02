<?php

require(__DIR__ . '/../../config.php');

use block_activity_copy_cart\app\copy\manager;
use block_activity_copy_cart\output\copy_progress_page;

require_login();

$jobid = required_param('jobid', PARAM_INT);
$job = manager::require_owned_job($jobid, (int) $USER->id);

$pageurl = new moodle_url('/blocks/activity_copy_cart/copy_progress.php', ['jobid' => $jobid]);
$courseurl = new moodle_url('/course/view.php', ['id' => $job->sourcecourseid]);

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('copyprogresstitle', 'block_activity_copy_cart'));
$PAGE->set_heading(get_string('copyprogresstitle', 'block_activity_copy_cart'));

$PAGE->requires->js_call_amd('block_activity_copy_cart/app/copy/progress', 'init', ['jobid' => $jobid]);

echo $OUTPUT->header();
echo $OUTPUT->render(new copy_progress_page($job, $courseurl));
echo $OUTPUT->footer();
