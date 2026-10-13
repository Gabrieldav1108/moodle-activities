<?php

namespace App\Actions;

use App\Enums\RunStatus;
use App\Models\Run;

class MarkStaleRuns
{
    /**
     * Fail runs whose worker stopped reporting (no log update for an hour).
     */
    public function __invoke(): int
    {
        return Run::query()
            ->where('status', RunStatus::Running)
            ->where('updated_at', '<', now()->subHour())
            ->update([
                'status' => RunStatus::Failed,
                'error' => 'Worker stopped responding.',
                'finished_at' => now(),
            ]);
    }
}
