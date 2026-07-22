<?php

namespace App\Services\Vk;

use App\Jobs\DispatchVkGroupScansJob;
use App\Models\ScanSetting;
use App\Models\VkGroup;
use Illuminate\Support\Facades\Log;

/**
 * Reads ScanSetting from DB and decides whether to fan-out group scans.
 * Invoked every minute by the Laravel scheduler tick.
 */
class ScanSchedule
{
    public function tick(): void
    {
        $settings = ScanSetting::current();

        if (! $settings->schedule_enabled) {
            Log::debug('vk.schedule.tick_disabled');

            return;
        }

        if (! $settings->isDue()) {
            Log::debug('vk.schedule.tick_not_due', [
                'interval_minutes' => $settings->normalizedIntervalMinutes(),
                'last_dispatched_at' => $settings->last_dispatched_at?->toIso8601String(),
            ]);

            return;
        }

        $active = VkGroup::query()->where('active', true)->count();
        $limit = $settings->normalizedLimit();
        $withComments = (bool) $settings->with_comments;

        DispatchVkGroupScansJob::dispatch(
            $limit,
            $withComments,
            null,
            'schedule',
        );

        $settings->markDispatched();

        $settings->logSnapshot('vk.schedule.dispatched', [
            'active_groups' => $active,
            'estimated_wave_seconds' => $settings->estimatedWaveSeconds($active),
        ]);
    }
}
