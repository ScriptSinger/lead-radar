<?php

use App\Services\Vk\ScanSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| VK scan schedule (DB-driven)
|--------------------------------------------------------------------------
|
| Tick every minute → ScanSchedule reads scan_settings (MoonShine) and
| dispatches DispatchVkGroupScansJob when interval_minutes has elapsed.
|
| Requires: php artisan schedule:work (compose service "scheduler")
|           + queue worker on queue "vk.scan".
|
*/
Schedule::call(static function (): void {
    app(ScanSchedule::class)->tick();
})
    ->name('vk-scan-schedule-tick')
    ->everyMinute()
    ->withoutOverlapping(5);
