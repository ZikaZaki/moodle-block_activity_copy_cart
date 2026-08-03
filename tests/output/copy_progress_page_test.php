<?php

namespace block_activity_copy_cart\output;

use block_activity_copy_cart\app\copy\repository;


final class copy_progress_page_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_export_for_template(): void {
        global $OUTPUT;
        $target = $this->getDataGenerator()->create_course(['fullname' => 'Target course']);
        $cart = ['sourcecourseid' => 3, 'items' => [10 => ['cmid' => 10, 'name' => 'My page']]];
        $jobid = repository::create_job(2, 3, $cart, [$target->id]);
        repository::add_result($jobid, 10, $target->id, 55, 'success', null);
        $job = repository::get_job($jobid);
        $courseurl = new \moodle_url('/course/view.php', ['id' => 3]);

        $data = (new copy_progress_page($job, $courseurl))->export_for_template($OUTPUT);

        $this->assertSame($jobid, $data['jobid']);
        $this->assertSame($courseurl->out(false), $data['courseurl']);
        $this->assertCount(1, $data['results']);
        $this->assertSame('My page', $data['results'][0]['activityname']);
    }
}
