<?php

namespace block_activity_copy_cart\app\copy;


final class job_test extends \basic_testcase {

    private function record(array $overrides = []): \stdClass {
        return (object) array_merge([
            'id' => '1',
            'userid' => '2',
            'sourcecourseid' => '3',
            'cart' => '{"sourcecourseid":3,"items":[]}',
            'targetcourseids' => '[4,5]',
            'status' => job::STATUS_PENDING,
            'totalunits' => '0',
            'completedunits' => '0',
            'failuremessage' => null,
            'timecreated' => '1000',
            'timemodified' => '1000',
        ], $overrides);
    }

    public function test_from_record_casts_types(): void {
        $job = job::from_record($this->record());

        $this->assertSame(1, $job->id);
        $this->assertSame(2, $job->userid);
        $this->assertSame(3, $job->sourcecourseid);
        $this->assertSame(0, $job->totalunits);
        $this->assertSame(0, $job->completedunits);
        $this->assertSame(1000, $job->timecreated);
        $this->assertSame(1000, $job->timemodified);
        $this->assertNull($job->failuremessage);
        $this->assertSame('{"sourcecourseid":3,"items":[]}', $job->cart);
        $this->assertSame('[4,5]', $job->targetcourseids);
    }

    public function test_from_record_preserves_failure_message(): void {
        $job = job::from_record($this->record(['status' => job::STATUS_FAILED, 'failuremessage' => 'boom']));

        $this->assertSame('boom', $job->failuremessage);
    }

    /**
     * @dataProvider terminal_status_provider
     */
    public function test_is_terminal(string $status, bool $expected): void {
        $job = job::from_record($this->record(['status' => $status]));

        $this->assertSame($expected, $job->is_terminal());
    }

    public static function terminal_status_provider(): array {
        return [
            'pending is not terminal' => [job::STATUS_PENDING, false],
            'running is not terminal' => [job::STATUS_RUNNING, false],
            'completed is terminal' => [job::STATUS_COMPLETED, true],
            'completed_with_errors is terminal' => [job::STATUS_COMPLETED_WITH_ERRORS, true],
            'failed is terminal' => [job::STATUS_FAILED, true],
        ];
    }
}
