<?php

use App\Jobs\DispatchVkGroupScansJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Phase 5 — scheduled VK scans
|--------------------------------------------------------------------------
|
| Requires a process running: php artisan schedule:work
| (docker service "scheduler") and queue worker on queue "vk.scan".
|
*/
$limit = max(1, min(30, (int) config('services.vk.scan_limit', 6)));
$withComments = (bool) config('services.vk.scan_with_comments', true);

Schedule::job(new DispatchVkGroupScansJob($limit, $withComments))
    ->name('vk-dispatch-group-scans')
    ->hourly()
    ->withoutOverlapping(55);
