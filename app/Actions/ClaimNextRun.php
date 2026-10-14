<?php

namespace App\Actions;

use App\Enums\AssignmentStatus;
use App\Enums\RunStatus;
use App\Models\Assignment;
use App\Models\Run;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ClaimNextRun
{
    /**
     * Mark the worker as seen and hand it the oldest pending run, if any.
     *
     * @return array<string, mixed>|null
     */
    public function handle(): ?array
    {
        $settings = Setting::current();
        $settings->forceFill(['worker_seen_at' => now()])->save();

        $run = DB::transaction(function () {
            $run = Run::query()
                ->where('status', RunStatus::Pending)
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            // The conditional update guards against two workers claiming the same run.
            $claimed = $run && Run::query()
                ->whereKey($run->id)
                ->where('status', RunStatus::Pending)
                ->update(['status' => RunStatus::Running, 'started_at' => now()]);

            return $claimed ? $run->refresh() : null;
        });

        if (! $run) {
            return null;
        }

        return [
            'run' => ['id' => $run->id, 'type' => $run->type->value],
            'engine' => $settings->engine->value,
            'model' => $run->model->value,
            'effort' => $run->effort->value,
            'force' => $run->force,
            'days_back' => $settings->days_back,
            'days_ahead' => $settings->days_ahead,
            'moodle' => [
                'url' => $settings->moodle_url,
                'username' => $settings->moodle_username,
                'password' => $settings->moodle_password,
            ],
            'anthropic_api_key' => $settings->anthropic_api_key,
            'target' => $run->assignment
                ? [
                    'cmid' => $run->assignment->cmid,
                    'event_id' => $run->assignment->event_id,
                    'name' => $run->assignment->name,
                    'course' => $run->assignment->course,
                    'module' => $run->assignment->module,
                    'url' => $run->assignment->url,
                    'due_at' => $run->assignment->due_at?->timestamp,
                    'overdue' => $run->assignment->overdue,
                ]
                : null,
            'done_cmids' => Assignment::query()
                ->whereIn('status', [AssignmentStatus::DraftReady, AssignmentStatus::Reviewed, AssignmentStatus::Submitted])
                ->pluck('cmid')
                ->all(),
        ];
    }
}
