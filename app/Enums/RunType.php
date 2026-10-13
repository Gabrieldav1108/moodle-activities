<?php

namespace App\Enums;

enum RunType: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Redo = 'redo';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Manual => __('Manual'),
            self::Redo => __('Redo'),
        };
    }
}
