<?php

namespace App\Services\Telegram;

use App\Jobs\DispatchTelegramChannelScansJob;
use App\Models\TelegramChannel;
use App\Models\TelegramScanSetting;

class TelegramScanSchedule
{
    public function tick(): void
    {
        $settings = TelegramScanSetting::current();
        if (! $settings->schedule_enabled || TelegramChannel::query()->where('active', true)->doesntExist()) {
            return;
        }
        if ($settings->last_dispatched_at && now()->diffInMinutes($settings->last_dispatched_at) < $settings->interval()) {
            return;
        }
        DispatchTelegramChannelScansJob::dispatch();
        $settings->forceFill(['last_dispatched_at' => now()])->save();
    }
}
