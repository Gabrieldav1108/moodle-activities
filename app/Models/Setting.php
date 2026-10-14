<?php

namespace App\Models;

use App\Enums\ClaudeModel;
use App\Enums\Effort;
use App\Enums\SolverEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row table holding the automation settings and credentials.
 *
 * @property int $id
 * @property bool $enabled
 * @property SolverEngine $engine
 * @property list<string> $run_times
 * @property list<int> $weekdays
 * @property ClaudeModel $model
 * @property Effort $effort
 * @property int $days_back
 * @property int $days_ahead
 * @property string $moodle_url
 * @property string|null $moodle_username
 * @property string|null $moodle_password
 * @property string|null $anthropic_api_key
 * @property Carbon|null $worker_seen_at
 */
#[Fillable([
    'enabled', 'engine', 'run_times', 'weekdays', 'model', 'effort', 'days_back', 'days_ahead',
    'moodle_url', 'moodle_username', 'moodle_password', 'anthropic_api_key', 'worker_seen_at',
])]
#[Hidden(['moodle_password', 'anthropic_api_key'])]
class Setting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'enabled' => false,
            'engine' => SolverEngine::ClaudeCode,
            'run_times' => ['07:00', '19:00'],
            'weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'model' => ClaudeModel::Opus55,
            'effort' => Effort::High,
            'days_back' => 30,
            'days_ahead' => 120,
            'moodle_url' => 'https://presencial.ifrs.edu.br',
        ]);
    }

    public function workerOnline(): bool
    {
        return $this->worker_seen_at?->gt(now()->subMinute()) ?? false;
    }

    /**
     * Next scheduled run times, in the app timezone.
     *
     * @return list<CarbonImmutable>
     */
    public function upcomingRuns(int $limit = 5): array
    {
        if (! $this->enabled || $this->run_times === [] || $this->weekdays === []) {
            return [];
        }

        $times = collect($this->run_times)->sort()->values();
        $upcoming = [];
        $day = now()->startOfDay();

        for ($i = 0; $i < 8 && count($upcoming) < $limit; $i++, $day = $day->copy()->addDay()) {
            if (! in_array($day->dayOfWeek, $this->weekdays, true)) {
                continue;
            }
            foreach ($times as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $at = $day->copy()->setTime($hour, $minute);
                if ($at->isFuture() && count($upcoming) < $limit) {
                    $upcoming[] = $at;
                }
            }
        }

        return $upcoming;
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'engine' => SolverEngine::class,
            'run_times' => 'array',
            'weekdays' => 'array',
            'model' => ClaudeModel::class,
            'effort' => Effort::class,
            'days_back' => 'integer',
            'days_ahead' => 'integer',
            'moodle_password' => 'encrypted',
            'anthropic_api_key' => 'encrypted',
            'worker_seen_at' => 'datetime',
        ];
    }
}
