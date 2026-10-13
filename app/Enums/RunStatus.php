<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Running], true);
    }
}
