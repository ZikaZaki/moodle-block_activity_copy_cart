<?php

namespace block_activity_copy_cart\app\copy;


final class repository_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_create_job_and_get_job_round_trip(): void {
        $cart = ['sourcecourseid' => 3, 'items' => [10 => ['cmid' => 10], 20 => ['cmid' => 20]]];
        $targetcourseids = [100, 200];

        $jobid = repository::create_job(2, 3, $cart, $targetcourseids);
        $job = repository::get_job($jobid);

        $this->assertNotNull($job);
        $this->assertSame(2, $job->userid);
        $this->assertSame(3, $job->sourcecourseid);
        $this->assertSame(job::STATUS_PENDING, $job->status);
        $this->assertSame(4, $job->totalunits);
        $this->assertSame(0, $job->completedunits);
        $this->assertNull($job->failuremessage);
        $this->assertEquals($cart, json_decode($job->cart, true));
        $this->assertSame($targetcourseids, json_decode($job->targetcourseids, true));
    }

    public function test_get_job_unknown_id_returns_null(): void {
        $this->assertNull(repository::get_job(999999));
    }

    public function test_update_job_changes_fields(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);

        repository::update_job($jobid, ['status' => job::STATUS_RUNNING, 'completedunits' => 5]);
        $job = repository::get_job($jobid);

        $this->assertSame(job::STATUS_RUNNING, $job->status);
        $this->assertSame(5, $job->completedunits);
    }

    public function test_create_job_backups_dedupes(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);

        repository::create_job_backups($jobid, [10, 10, 20]);

        $this->assertCount(2, repository::get_pending_job_backups($jobid));
        $this->assertSame(2, repository::count_pending_job_backups($jobid));
    }

    public function test_update_job_backup_and_lookup_by_cmid(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::create_job_backups($jobid, [10, 20]);

        $bycmid = repository::get_job_backups_by_cmid($jobid);
        repository::update_job_backup($bycmid[10]->id, ['status' => 'done', 'backupid' => 'abc123']);

        $this->assertSame(1, repository::count_pending_job_backups($jobid));

        $bycmid = repository::get_job_backups_by_cmid($jobid);
        $this->assertSame('done', $bycmid[10]->status);
        $this->assertSame('abc123', $bycmid[10]->backupid);
        $this->assertSame('pending', $bycmid[20]->status);
    }

    public function test_add_result_and_counts_with_mixed_outcomes(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);

        repository::add_result($jobid, 10, 100, 555, 'success', null);
        repository::add_result($jobid, 10, 200, null, 'failed', 'oops');

        $this->assertSame(2, repository::count_results($jobid));
        $this->assertSame(2, repository::count_results_for_cmid($jobid, 10));
        $this->assertTrue(repository::has_incomplete_results($jobid));

        $processed = repository::get_processed_pairs($jobid);
        $this->assertArrayHasKey('10-100', $processed);
        $this->assertArrayHasKey('10-200', $processed);

        $results = repository::get_results($jobid);
        $this->assertCount(2, $results);
        $this->assertSame('failed', $results[0]->status);
        $this->assertSame('success', $results[1]->status);
    }

    public function test_has_incomplete_results_false_when_all_succeeded(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::add_result($jobid, 10, 100, 555, 'success', null);

        $this->assertFalse(repository::has_incomplete_results($jobid));
    }

    public function test_delete_job_cascades(): void {
        $jobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::create_job_backups($jobid, [10]);
        repository::add_result($jobid, 10, 100, 555, 'success', null);

        repository::delete_job($jobid);

        $this->assertNull(repository::get_job($jobid));
        $this->assertSame([], repository::get_pending_job_backups($jobid));
        $this->assertSame([], repository::get_results($jobid));
    }

    public function test_get_and_delete_jobs_for_user_is_scoped_to_that_user(): void {
        $jobid1 = repository::create_job(1, 3, ['sourcecourseid' => 3, 'items' => []], []);
        $jobid2 = repository::create_job(1, 3, ['sourcecourseid' => 3, 'items' => []], []);
        $otherjobid = repository::create_job(2, 3, ['sourcecourseid' => 3, 'items' => []], []);

        $this->assertCount(2, repository::get_jobs_for_user(1));

        repository::delete_jobs_for_user(1);

        $this->assertSame([], repository::get_jobs_for_user(1));
        $this->assertNull(repository::get_job($jobid1));
        $this->assertNull(repository::get_job($jobid2));
        $this->assertNotNull(repository::get_job($otherjobid));
    }
}
