<?php

namespace block_activity_copy_cart\app\copy;

use block_activity_copy_cart\app\block\item_settings;
use block_activity_copy_cart\app\block\manager as cart_manager;
use block_activity_copy_cart\exception\exception;
use block_activity_copy_cart\task\backup_task;


final class manager_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_require_owned_job_allows_owner(): void {
        $owner = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($owner->id, 3, ['sourcecourseid' => 3, 'items' => []], []);

        $job = manager::require_owned_job($jobid, $owner->id);

        $this->assertSame($jobid, $job->id);
    }

    public function test_require_owned_job_allows_site_admin(): void {
        global $USER;

        $owner = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($owner->id, 3, ['sourcecourseid' => 3, 'items' => []], []);

        $this->setAdminUser();
        $job = manager::require_owned_job($jobid, (int) $USER->id);

        $this->assertSame($jobid, $job->id);
    }

    public function test_require_owned_job_denies_other_user(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($owner->id, 3, ['sourcecourseid' => 3, 'items' => []], []);

        $this->expectException(exception::class);
        manager::require_owned_job($jobid, $other->id);
    }

    public function test_require_owned_job_unknown_id_throws(): void {
        $this->expectException(exception::class);
        manager::require_owned_job(999999, 1);
    }

    public function test_job_context_percent_and_terminal_flag(): void {
        $jobid = repository::create_job(1, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::update_job($jobid, ['totalunits' => 3, 'completedunits' => 1, 'status' => job::STATUS_RUNNING]);
        $job = repository::get_job($jobid);

        $context = manager::job_context($job);

        $this->assertSame($jobid, $context['jobid']);
        $this->assertSame(33, $context['percent']);
        $this->assertFalse($context['isterminal']);

        $emptyjobid = repository::create_job(1, 3, ['sourcecourseid' => 3, 'items' => []], []);
        $emptyjob = repository::get_job($emptyjobid);
        $this->assertSame(0, manager::job_context($emptyjob)['percent']);
    }

    public function test_status_label_covers_every_status(): void {
        $statuses = [
            job::STATUS_PENDING,
            job::STATUS_RUNNING,
            job::STATUS_COMPLETED,
            job::STATUS_COMPLETED_WITH_ERRORS,
            job::STATUS_FAILED,
        ];

        $labels = array_map([manager::class, 'status_label'], $statuses);

        foreach ($labels as $label) {
            $this->assertNotSame('', $label);
        }
        $this->assertSame(count($statuses), count(array_unique($labels)), 'Every status must have its own distinct label.');
    }

    public function test_create_job_seeds_backups_and_queues_task(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $cart = ['sourcecourseid' => 3, 'items' => [10 => ['cmid' => 10], 20 => ['cmid' => 20]]];
        $jobid = manager::create_job($cart, [100, 200]);

        $job = repository::get_job($jobid);

        $this->assertEquals($user->id, $job->userid);
        $this->assertSame(4, $job->totalunits);
        $this->assertSame(2, repository::count_pending_job_backups($jobid));

        $queued = \core\task\manager::get_adhoc_tasks(backup_task::class);
        $matching = array_filter($queued, fn($task) => (int) $task->get_custom_data()->jobid === $jobid);
        $this->assertNotEmpty($matching, 'create_job() must queue a backup_task for this job.');
    }

    public function test_mark_job_failed_sets_status_and_message(): void {
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $jobid = repository::create_job($owner->id, $course->id, ['sourcecourseid' => $course->id, 'items' => []], []);

        $sink = $this->redirectMessages();
        manager::mark_job_failed($jobid, 'source capability lost');
        $messages = $sink->get_messages();
        $sink->close();

        $job = repository::get_job($jobid);
        $this->assertSame(job::STATUS_FAILED, $job->status);
        $this->assertSame('source capability lost', $job->failuremessage);

        $this->assertCount(1, $messages);
        $this->assertSame('copycompleted', $messages[0]->eventtype);
        $this->assertEquals($owner->id, $messages[0]->useridto);
    }

    public function test_target_courses_formats_names(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Target course']);
        $this->assertSame([], manager::target_courses([]));
        $rows = manager::target_courses([$course->id]);
        $this->assertEquals([['id' => $course->id, 'fullname' => 'Target course']], $rows);
    }

    public function test_result_rows_joins_activity_and_course_names(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Target course']);
        $cart = ['sourcecourseid' => 3, 'items' => [10 => ['cmid' => 10, 'name' => 'Source activity']]];
        $jobid = repository::create_job(1, 3, $cart, [$course->id]);
        repository::add_result($jobid, 10, $course->id, 555, 'success', null);
        $job = repository::get_job($jobid);

        $rows = manager::result_rows($job, repository::get_results($jobid));

        $this->assertCount(1, $rows);
        $this->assertSame('Source activity', $rows[0]['activityname']);
        $this->assertSame('Target course', $rows[0]['coursefullname']);
        $this->assertSame(get_string('resultsuccess', 'block_activity_copy_cart'), $rows[0]['statuslabel']);
    }

    public function test_full_pipeline_backs_up_and_restores_into_target_course(): void {
        global $DB;

        $this->setAdminUser();

        $sourcecourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'Pipeline page',
        ]);

        $cart = cart_manager::build([$page->cmid], []);
        $jobid = manager::create_job($cart, [$targetcourse->id]);

        manager::process_backups($jobid);
        manager::process_restores($jobid);

        $job = repository::get_job($jobid);
        $this->assertSame(job::STATUS_COMPLETED, $job->status);
        $this->assertSame(1, $job->completedunits);

        $results = repository::get_results($jobid);
        $this->assertCount(1, $results);
        $this->assertSame('success', $results[0]->status);
        $this->assertNotNull($results[0]->newcmid);

        $newcm = get_coursemodule_from_id('page', (int) $results[0]->newcmid, $targetcourse->id, false, MUST_EXIST);
        $this->assertSame('Pipeline page', $DB->get_field('page', 'name', ['id' => $newcm->instance]));
    }

    public function test_negative_section_number_is_skipped_not_created(): void {
        global $DB;

        $this->setAdminUser();

        $sourcecourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'Malicious section page',
        ]);

        $cart = cart_manager::build([$page->cmid], [
            (string) $page->cmid => [
                'sectionmatch' => item_settings::SECTION_MATCH_POSITION,
                'section' => -5,
                'sectionmissing' => item_settings::SECTION_MISSING_CREATE,
            ],
        ]);
        $jobid = manager::create_job($cart, [$targetcourse->id]);

        manager::process_backups($jobid);
        manager::process_restores($jobid);

        $results = repository::get_results($jobid);
        $this->assertCount(1, $results);
        $this->assertSame('skipped', $results[0]->status);
        $this->assertFalse(
            $DB->record_exists('course_sections', ['course' => $targetcourse->id, 'section' => -5]),
            'A negative section number must never be inserted into course_sections.'
        );
    }

    public function test_recover_stalled_jobs_requeues_backup_task_when_backups_pending(): void {
        $user = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($user->id, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::create_job_backups($jobid, [10]);
        // Simulate a job whose adhoc task died without ever touching it again.
        global $DB;
        $DB->set_field('block_activity_copy_cart_job', 'timemodified', time() - (4 * HOURSECS), ['id' => $jobid]);

        $recovered = manager::recover_stalled_jobs(3 * HOURSECS);

        $this->assertSame(1, $recovered);
        $queued = \core\task\manager::get_adhoc_tasks(backup_task::class);
        $matching = array_filter($queued, fn($task) => (int) $task->get_custom_data()->jobid === $jobid);
        $this->assertNotEmpty($matching, 'A stalled job with pending backups must get a fresh backup_task queued.');
    }

    public function test_recover_stalled_jobs_ignores_recently_touched_jobs(): void {
        $user = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($user->id, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::create_job_backups($jobid, [10]);

        $recovered = manager::recover_stalled_jobs(3 * HOURSECS);

        $this->assertSame(0, $recovered, 'A job touched moments ago must not be treated as stalled.');
    }

    public function test_recover_stalled_jobs_ignores_terminal_jobs(): void {
        $user = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($user->id, 3, ['sourcecourseid' => 3, 'items' => []], []);
        repository::update_job($jobid, ['status' => job::STATUS_COMPLETED]);
        global $DB;
        $DB->set_field('block_activity_copy_cart_job', 'timemodified', time() - (4 * HOURSECS), ['id' => $jobid]);

        $recovered = manager::recover_stalled_jobs(3 * HOURSECS);

        $this->assertSame(0, $recovered, 'A completed job must never be requeued, no matter how old.');
    }

    public function test_wildly_out_of_range_section_number_is_skipped_not_created(): void {
        global $DB;

        $this->setAdminUser();

        $sourcecourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'Malicious section page',
        ]);

        $cart = cart_manager::build([$page->cmid], [
            (string) $page->cmid => [
                'sectionmatch' => item_settings::SECTION_MATCH_POSITION,
                'section' => 999999,
                'sectionmissing' => item_settings::SECTION_MISSING_CREATE,
            ],
        ]);
        $jobid = manager::create_job($cart, [$targetcourse->id]);

        manager::process_backups($jobid);
        manager::process_restores($jobid);

        $results = repository::get_results($jobid);
        $this->assertCount(1, $results);
        $this->assertSame('skipped', $results[0]->status);
        $this->assertFalse(
            $DB->record_exists('course_sections', ['course' => $targetcourse->id, 'section' => 999999]),
            'A wildly out-of-range section number must never be inserted into course_sections.'
        );
    }
}
