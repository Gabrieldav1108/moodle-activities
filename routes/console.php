<?php

use App\Actions\MarkStaleRuns;
use App\Actions\ScheduleRuns;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(new ScheduleRuns)->everyMinute()->name('schedule-runs')->withoutOverlapping();
Schedule::call(new MarkStaleRuns)->everyTenMinutes()->name('mark-stale-runs');
