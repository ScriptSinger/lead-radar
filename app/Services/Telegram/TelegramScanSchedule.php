<?php

namespace App\Services\Telegram;

use App\Jobs\DispatchTelegramChannelScansJob;
use App\Models\TelegramChannel;
use App\Models\TelegramScanSetting;
use Illuminate\Support\Facades\Log;

class TelegramScanSchedule
{
    public function tick(): void
    {
        TelegramScanSetting::query()->orderBy('workspace_id')->each(
            fn (TelegramScanSetting $settings): void => $this->tickWorkspace($settings),
        );
    }

    private function tickWorkspace(TelegramScanSetting $settings): void
    {
        if (! $settings->schedule_enabled) {
            Log::debug('telegram.schedule.tick_disabled');

            return;
        }

        $activeChannels = TelegramChannel::query()->where('workspace_id', $settings->workspace_id)->where('active', true)->count();
        if ($activeChannels === 0) {
            Log::warning('telegram.schedule.no_active_channels');

            return;
        }

        if ($settings->last_dispatched_at && $settings->last_dispatched_at->diffInMinutes(now()) < $settings->interval()) {
            Log::debug('telegram.schedule.tick_not_due', [
                'interval_minutes' => $settings->interval(),
                'last_dispatched_at' => $settings->last_dispatched_at->toIso8601String(),
            ]);

            return;
        }

        DispatchTelegramChannelScansJob::dispatch(workspaceId: (int) $settings->workspace_id);
        $settings->forceFill(['last_dispatched_at' => now()])->save();

        Log::info('telegram.schedule.dispatched', [
            'interval_minutes' => $settings->interval(),
            'channel_delay_seconds' => (int) $settings->channel_delay_seconds,
            'scan_limit' => $settings->limit(),
            'with_comments' => (bool) $settings->with_comments,
            'comments_limit' => (int) $settings->comments_limit,
            'active_channels' => $activeChannels,
        ]);
    }
}
