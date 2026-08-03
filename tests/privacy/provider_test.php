<?php

namespace block_activity_copy_cart\privacy;

use block_activity_copy_cart\app\copy\repository;
use core_privacy\local\request\writer;


final class provider_test extends \core_privacy\tests\provider_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_metadata_declares_every_table(): void {
        $collection = new \core_privacy\local\metadata\collection('block_activity_copy_cart');
        $collection = provider::get_metadata($collection);

        $tables = array_map(
            fn($item): string => $item->get_name(),
            $collection->get_collection()
        );

        $this->assertContains('block_activity_copy_cart_job', $tables);
        $this->assertContains('block_activity_copy_cart_bkp', $tables);
        $this->assertContains(
            'block_activity_copy_cart_res',
            $tables,
            'The per-unit results table must be declared: it stores personal data too.'
        );
    }

    public function test_export_includes_per_unit_results(): void {
        $user = $this->getDataGenerator()->create_user();
        $jobid = repository::create_job($user->id, 3, ['sourcecourseid' => 3, 'items' => []], [5]);
        repository::add_result($jobid, 10, 5, 55, 'success', null);

        $this->export_all_data_for_user($user->id, 'block_activity_copy_cart');

        $context = \context_user::instance($user->id);
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'block_activity_copy_cart')]);

        $this->assertNotEmpty($data->jobs);
        $exportedjob = $data->jobs[0];
        $this->assertCount(1, $exportedjob->results, 'An export must include the job\'s per-unit results, not just the job row.');
        $this->assertSame(10, $exportedjob->results[0]->sourcecmid);
        $this->assertSame(5, $exportedjob->results[0]->targetcourseid);
        $this->assertSame('success', $exportedjob->results[0]->status);
    }
}
