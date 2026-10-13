<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Downloaded = 'downloaded';
    case DraftReady = 'draft_ready';
    case Failed = 'failed';
    case Reviewed = 'reviewed';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Downloaded => __('Downloaded'),
            self::DraftReady => __('Draft ready'),
            self::Failed => __('Failed'),
            self::Reviewed => __('Reviewed'),
            self::Submitted => __('Submitted'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Downloaded => 'zinc',
            self::DraftReady => 'blue',
            self::Failed => 'red',
            self::Reviewed => 'amber',
            self::Submitted => 'green',
        };
    }

    /**
     * Statuses set by the user that a new worker result must not overwrite.
     */
    public function isManual(): bool
    {
        return in_array($this, [self::Reviewed, self::Submitted], true);
    }
}
