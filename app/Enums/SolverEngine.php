<?php

namespace App\Enums;

enum SolverEngine: string
{
    case ClaudeCode = 'claude_code';
    case Api = 'api';
    case Gemini = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::ClaudeCode => __('Claude Code on this machine (subscription)'),
            self::Api => __('Anthropic API (billed per use)'),
            self::Gemini => __('Google Gemini (free tier)'),
        };
    }

    /**
     * Suggested model IDs; any other ID the provider accepts can be typed in.
     *
     * @return array<string, string>
     */
    public function models(): array
    {
        return match ($this) {
            self::ClaudeCode, self::Api => [
                'claude-opus-5-5' => 'Claude Opus 5.5',
                'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
                'claude-haiku-5-5' => 'Claude Haiku 5.5',
                'claude-fable-5-1' => 'Claude Fable 5.1',
            ],
            self::Gemini => [
                'gemini-3.8-flash' => 'Gemini 3.8 Flash',
                'gemini-3.6-flash' => 'Gemini 3.6 Flash',
                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            ],
        };
    }

    /**
     * Model IDs are free text (passed to a CLI/SDK), so only allow ID-like characters.
     *
     * @return list<string>
     */
    public static function modelRules(): array
    {
        return ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/'];
    }

    public function defaultModel(): string
    {
        return array_key_first($this->models());
    }

    /**
     * Name of the Setting attribute that holds this engine's API key, if it needs one.
     */
    public function apiKeyAttribute(): ?string
    {
        return match ($this) {
            self::ClaudeCode => null,
            self::Api => 'anthropic_api_key',
            self::Gemini => 'gemini_api_key',
        };
    }

    public static function modelLabel(?string $model): string
    {
        foreach (self::cases() as $engine) {
            if (isset($engine->models()[$model])) {
                return $engine->models()[$model];
            }
        }

        return (string) $model;
    }
}
