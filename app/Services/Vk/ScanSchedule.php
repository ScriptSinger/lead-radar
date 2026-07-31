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

        $this->dispatchWave(trigger: 'schedule', markDispatched: true);
    }

    /**
     * Force a fan-out now (admin "Run scan now" / CLI). Ignores interval due check.
     *
     * @return array{dispatched: bool, active_groups: int, limit: int, with_comments: bool}
     */
    public function dispatchNow(string $trigger = 'admin'): array
    {
        return $this->dispatchWave(trigger: $trigger, markDispatched: true);
    }

    /**
     * @return array{dispatched: bool, active_groups: int, limit: int, with_comments: bool}
     */
    private function dispatchWave(string $trigger, bool $markDispatched): array
    {
        $settings = ScanSetting::current();
        $active = VkGroup::query()->where('active', true)->count();
        $limit = $settings->normalizedLimit();
        $withComments = (bool) $settings->with_comments;

        if ($active === 0) {
            Log::warning('vk.schedule.no_active_groups', ['trigger' => $trigger]);
            // Still advance last_dispatched so the tick does not retry every minute empty.
            if ($markDispatched) {
                $settings->markDispatched();
            }

            return [
                'dispatched' => false,
                'active_groups' => 0,
                'limit' => $limit,
                'with_comments' => $withComments,
            ];
        }

        DispatchVkGroupScansJob::dispatch(
            $limit,
            $withComments,
            null,
            $trigger,
        );

        if ($markDispatched) {
            $settings->markDispatched();
        }

        $settings->logSnapshot('vk.schedule.dispatched', [
            'trigger' => $trigger,
            'active_groups' => $active,
            'estimated_wave_seconds' => $settings->estimatedWaveSeconds($active),
        ]);

        return [
            'dispatched' => true,
            'active_groups' => $active,
            'limit' => $limit,
            'with_comments' => $withComments,
        ];
    }
}
