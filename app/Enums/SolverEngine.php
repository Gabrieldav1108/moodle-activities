<?php

namespace App\Enums;

enum SolverEngine: string
{
    case ClaudeCode = 'claude_code';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::ClaudeCode => __('Claude Code on this machine (subscription)'),
            self::Api => __('Anthropic API (billed per use)'),
        };
    }
}
