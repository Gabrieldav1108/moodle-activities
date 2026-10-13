<?php

namespace App\Actions;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\Run;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreAssignmentResult
{
    /**
     * Upsert an assignment from a worker result, replacing its attachments and draft.
     *
     * @param  array<string, mixed>  $meta
     * @param  list<array{path: string, content: string|null}>  $draft
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(Run $run, array $meta, array $draft, array $attachments): Assignment
    {
        return DB::transaction(function () use ($run, $meta, $draft, $attachments) {
            $assignment = Assignment::query()->firstOrNew(['cmid' => $meta['cmid']]);
            $status = AssignmentStatus::from($meta['status']);
            $hasDraft = $status === AssignmentStatus::DraftReady;

            // Never overwrite a status the user set by hand (reviewed/submitted).
            if ($assignment->exists && $assignment->status->isManual()) {
                $status = $assignment->status;
            }

            $assignment->fill([
                'event_id' => $meta['event_id'] ?? null,
                'name' => $meta['name'],
                'course' => $meta['course'],
                'module' => $meta['module'],
                'due_at' => isset($meta['due_at']) ? Carbon::createFromTimestamp($meta['due_at']) : null,
                'overdue' => $meta['overdue'] ?? false,
                'url' => $meta['url'],
                'statement_md' => $meta['statement_md'] ?? null,
                'statement_html' => $meta['statement_html'] ?? null,
                'status' => $status,
                'error' => $meta['error'] ?? null,
                'last_run_id' => $run->id,
            ]);

            if ($hasDraft) {
                $assignment->fill([
                    'summary' => $meta['summary'] ?? null,
                    'review_notes' => $meta['review_notes'] ?? null,
                    'model' => $run->model,
                    'effort' => $run->effort,
                    'generated_at' => now(),
                ]);
            }
            $assignment->save();

            if ($attachments !== []) {
                $this->replaceAttachments($assignment, $attachments);
            }
            if ($hasDraft) {
                $assignment->draftFiles()->delete();
                foreach ($draft as $file) {
                    $assignment->files()->create([
                        'kind' => AssignmentFile::KIND_DRAFT,
                        'path' => preg_replace('#^(\./)+#', '', str_replace('\\', '/', $file['path'])),
                        'content' => $file['content'] ?? '',
                    ]);
                }
            }

            return $assignment;
        });
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    private function replaceAttachments(Assignment $assignment, array $attachments): void
    {
        $dir = $assignment->storageDir().'/attachments';
        Storage::deleteDirectory($dir);
        $assignment->attachments()->delete();

        foreach ($attachments as $upload) {
            $name = basename(str_replace('\\', '/', $upload->getClientOriginalName())) ?: 'file';
            $assignment->files()->create([
                'kind' => AssignmentFile::KIND_ATTACHMENT,
                'path' => $name,
                'disk_path' => $upload->storeAs($dir, $name),
            ]);
        }
    }
}
