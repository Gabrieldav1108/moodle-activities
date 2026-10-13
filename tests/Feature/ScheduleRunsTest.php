<?php

namespace Tests\Feature;

use App\Actions\MarkStaleRuns;
use App\Actions\ScheduleRuns;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Models\Run;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_a_run_at_a_configured_time(): void
    {
        Setting::current()->update(['enabled' => true, 'run_times' => ['07:00'], 'weekdays' => [1]]);
        $monday = Carbon::parse('2026-10-12 07:00:20');

        $run = (new ScheduleRuns)->handle($monday);

        $this->assertNotNull($run);
        $this->assertSame(RunType::Scheduled, $run->type);
        $this->assertNull((new ScheduleRuns)->handle($monday->copy()->addSeconds(30)), 'no duplicate in the same minute');
    }

    public function test_ignores_other_times_days_and_disabled_settings(): void
    {
        Setting::current()->update(['enabled' => true, 'run_times' => ['07:00'], 'weekdays' => [1]]);

        $this->assertNull((new ScheduleRuns)->handle(Carbon::parse('2026-10-12 07:01')));
        $this->assertNull((new ScheduleRuns)->handle(Carbon::parse('2026-10-13 07:00')));

        Setting::current()->update(['enabled' => false]);
        $this->assertNull((new ScheduleRuns)->handle(Carbon::parse('2026-10-12 07:00')));
        $this->assertSame(0, Run::query()->count());
    }

    public function test_upcoming_runs_respect_weekdays(): void
    {
        Carbon::setTestNow('2026-10-13 08:00'); // Tuesday
        $settings = Setting::current();
        $settings->update(['enabled' => true, 'run_times' => ['19:00', '07:00'], 'weekdays' => [2, 4]]);

        $upcoming = array_map(fn ($at) => $at->format('D H:i'), $settings->upcomingRuns(3));

        $this->assertSame(['Tue 19:00', 'Thu 07:00', 'Thu 19:00'], $upcoming);
    }

    public function test_marks_stale_running_runs_as_failed(): void
    {
        $run = Run::queue(RunType::Manual);
        $run->update(['status' => RunStatus::Running]);
        Run::query()->whereKey($run->id)->update(['updated_at' => now()->subHours(2)]);

        $this->assertSame(1, (new MarkStaleRuns)());
        $this->assertSame(RunStatus::Failed, $run->fresh()->status);
    }
}
