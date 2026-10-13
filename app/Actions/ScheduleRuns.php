<?php

namespace App\Actions;

use App\Enums\RunType;
use App\Models\Run;
use App\Models\Setting;
use Carbon\CarbonInterface;

class ScheduleRuns
{
    public function __invoke(): void
    {
        $this->handle(now());
    }

    /**
     * Called every minute: queue a run when the current time matches the settings.
     */
    public function handle(CarbonInterface $now): ?Run
    {
        $settings = Setting::current();

        if (! $settings->enabled
            || ! in_array($now->dayOfWeek, $settings->weekdays, true)
            || ! in_array($now->format('H:i'), $settings->run_times, true)) {
            return null;
        }

        $alreadyQueued = Run::query()->active()->exists()
            || Run::query()
                ->where('type', RunType::Scheduled)
                ->where('created_at', '>=', $now->copy()->startOfMinute())
                ->exists();

        return $alreadyQueued ? null : Run::queue(RunType::Scheduled);
    }
}
