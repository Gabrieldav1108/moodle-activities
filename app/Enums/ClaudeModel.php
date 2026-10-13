<?php

namespace App\Enums;

enum ClaudeModel: string
{
    case Opus55 = 'claude-opus-5-5';
    case Sonnet55 = 'claude-sonnet-5-5';
    case Haiku55 = 'claude-haiku-5-5';
    case Fable51 = 'claude-fable-5-1';

    public function label(): string
    {
        return match ($this) {
            self::Opus55 => 'Claude Opus 5.5',
            self::Sonnet55 => 'Claude Sonnet 5.5',
            self::Haiku55 => 'Claude Haiku 5.5',
            self::Fable51 => 'Claude Fable 5.1',
        };
    }
}
