<?php

namespace block_activity_copy_cart\external;

use block_activity_copy_cart\app\copy\job;
use block_activity_copy_cart\app\copy\repository;
use block_activity_copy_cart\exception\exception;


final class get_job_progress_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_returns_progress_and_results_for_the_owner(): void {
        $owner = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_course(['fullname' => 'Target course']);
        $cart = ['sourcecourseid' => 3, 'items' => [10 => ['cmid' => 10, 'name' => 'My page']]];
        $jobid = repository::create_job($owner->id, 3, $cart, [$target->id]);
        repository::add_result($jobid, 10, $target->id, 55, 'success', null);
        repository::update_job($jobid, ['completedunits' => 1, 'status' => job::STATUS_COMPLETED]);
        $this->setUser($owner);

        $result = get_job_progress::execute($jobid);

        $this->assertSame($jobid, $result['jobid']);
        $this->assertSame(job::STATUS_COMPLETED, $result['status']);
        $this->assertTrue($result['isterminal']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('My page', $result['results'][0]['activityname']);
    }

    public function test_throws_for_a_job_owned_by_someone_else(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($owner->id, 3, ['sourcecourseid' => 3, 'items' => []], []);
        $this->setUser($other);

        $this->expectException(exception::class);
        get_job_progress::execute($jobid);
    }
}
