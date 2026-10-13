<?php

namespace App\Enums;

enum Effort: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case XHigh = 'xhigh';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
            self::XHigh => __('Extra high'),
            self::Max => __('Max'),
        };
    }
}
