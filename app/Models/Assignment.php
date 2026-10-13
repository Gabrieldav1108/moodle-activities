<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\ClaudeModel;
use App\Enums\Effort;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Moodle activity processed by the worker.
 *
 * @property int $id
 * @property int $cmid
 * @property int|null $event_id
 * @property string $name
 * @property string $course
 * @property string $module
 * @property Carbon|null $due_at
 * @property bool $overdue
 * @property string $url
 * @property string|null $statement_md
 * @property string|null $statement_html
 * @property string|null $summary
 * @property string|null $review_notes
 * @property AssignmentStatus $status
 * @property ClaudeModel|null $model
 * @property Effort|null $effort
 * @property Carbon|null $generated_at
 * @property string|null $error
 * @property int|null $last_run_id
 * @property-read Collection<int, AssignmentFile> $files
 * @property-read Collection<int, AssignmentFile> $attachments
 * @property-read Collection<int, AssignmentFile> $draftFiles
 */
#[Fillable([
    'cmid', 'event_id', 'name', 'course', 'module', 'due_at', 'overdue', 'url',
    'statement_md', 'statement_html', 'summary', 'review_notes', 'status',
    'model', 'effort', 'generated_at', 'error', 'last_run_id',
])]
class Assignment extends Model
{
    public function storageDir(): string
    {
        return "assignments/{$this->cmid}";
    }

    public function isPastDue(): bool
    {
        return $this->due_at?->isPast() ?? false;
    }

    /**
     * @return HasMany<AssignmentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(AssignmentFile::class);
    }

    /**
     * @return HasMany<AssignmentFile, $this>
     */
    public function attachments(): HasMany
    {
        return $this->files()->where('kind', AssignmentFile::KIND_ATTACHMENT)->orderBy('path');
    }

    /**
     * @return HasMany<AssignmentFile, $this>
     */
    public function draftFiles(): HasMany
    {
        return $this->files()->where('kind', AssignmentFile::KIND_DRAFT)->orderBy('path');
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'overdue' => 'boolean',
            'status' => AssignmentStatus::class,
            'model' => ClaudeModel::class,
            'effort' => Effort::class,
            'generated_at' => 'datetime',
        ];
    }
}
