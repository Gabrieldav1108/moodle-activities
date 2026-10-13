<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attachment (binary, stored on disk) or a draft file (text, stored in the DB).
 *
 * @property int $id
 * @property int $assignment_id
 * @property string $kind
 * @property string $path
 * @property string|null $content
 * @property string|null $disk_path
 * @property-read Assignment $assignment
 */
#[Fillable(['assignment_id', 'kind', 'path', 'content', 'disk_path'])]
class AssignmentFile extends Model
{
    public const KIND_ATTACHMENT = 'attachment';

    public const KIND_DRAFT = 'draft';

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
