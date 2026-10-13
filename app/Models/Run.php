<?php

namespace App\Models;

use App\Enums\ClaudeModel;
use App\Enums\Effort;
use App\Enums\RunStatus;
use App\Enums\RunType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for the Python worker to process assignments.
 *
 * @property int $id
 * @property RunType $type
 * @property RunStatus $status
 * @property ClaudeModel $model
 * @property Effort $effort
 * @property bool $force
 * @property int|null $assignment_id
 * @property string|null $log
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Assignment|null $assignment
 */
#[Fillable(['type', 'status', 'model', 'effort', 'force', 'assignment_id', 'log', 'error', 'started_at', 'finished_at'])]
class Run extends Model
{
    /**
     * Queue a run with the current settings (or the given overrides).
     */
    public static function queue(
        RunType $type,
        ?ClaudeModel $model = null,
        ?Effort $effort = null,
        ?Assignment $assignment = null,
        bool $force = false,
    ): self {
        $settings = Setting::current();

        return self::query()->create([
            'type' => $type,
            'status' => RunStatus::Pending,
            'model' => $model ?? $settings->model,
            'effort' => $effort ?? $settings->effort,
            'assignment_id' => $assignment?->id,
            'force' => $force || $assignment !== null,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [RunStatus::Pending, RunStatus::Running]);
    }

    /**
     * @param  list<string>  $lines
     */
    public function appendLog(array $lines): void
    {
        if ($lines === []) {
            // Heartbeat: keeps the run from being flagged as stale.
            $this->touch();

            return;
        }

        $this->log = ltrim(($this->log ?? '')."\n".implode("\n", $lines), "\n");
        $this->save();
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    protected function casts(): array
    {
        return [
            'type' => RunType::class,
            'status' => RunStatus::class,
            'model' => ClaudeModel::class,
            'effort' => Effort::class,
            'force' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
