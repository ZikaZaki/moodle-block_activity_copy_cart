<?php

namespace block_activity_copy_cart\app\copy;


/**
 * Value object for one copy job's database record.
 */
final class job {
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public int $id,
        public int $userid,
        public int $sourcecourseid,
        public string $cart,
        public string $targetcourseids,
        public string $status,
        public int $totalunits,
        public int $completedunits,
        public ?string $failuremessage,
        public int $timecreated,
        public int $timemodified,
    ) {
    }

    public static function from_record(\stdClass $record): self {
        return new self(
            (int) $record->id,
            (int) $record->userid,
            (int) $record->sourcecourseid,
            $record->cart,
            $record->targetcourseids,
            $record->status,
            (int) $record->totalunits,
            (int) $record->completedunits,
            $record->failuremessage,
            (int) $record->timecreated,
            (int) $record->timemodified,
        );
    }

    public function is_terminal(): bool {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS, self::STATUS_FAILED], true);
    }
}
