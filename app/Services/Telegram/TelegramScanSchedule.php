<?php

namespace App\Services\Telegram;

use App\Jobs\DispatchTelegramChannelScansJob;
use App\Models\TelegramChannel;
use Illuminate\Support\Facades\Cache;

class TelegramScanSchedule
{
    private const LAST_DISPATCH_KEY = 'telegram.scan.last_dispatched_at';

    public function tick(): void
    {
        if (! config('services.telegram.scan.enabled', false) || TelegramChannel::query()->where('active', true)->doesntExist()) {
            return;
        }
        $interval = max(1, min(1440, (int) config('services.telegram.scan.interval_minutes', 30)));
        $last = Cache::get(self::LAST_DISPATCH_KEY);
        if ($last && now()->diffInMinutes($last) < $interval) {
            return;
        }
        DispatchTelegramChannelScansJob::dispatch();
        Cache::forever(self::LAST_DISPATCH_KEY, now());
    }
}
