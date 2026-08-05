<?php

namespace App\Jobs;

use App\Models\TelegramChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchTelegramChannelScansJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $limit = null, public ?int $onlyChannelId = null, public string $trigger = 'schedule')
    {
        $this->onConnection('redis')->onQueue('telegram.scan');
    }

    public function handle(): void
    {
        $limit = max(1, min(100, $this->limit ?? (int) config('services.telegram.scan.limit', 20)));
        $delay = max(0, (int) config('services.telegram.scan.channel_delay_seconds', 3));
        $seconds = 0;
        TelegramChannel::query()->where('active', true)->when($this->onlyChannelId, fn ($q) => $q->whereKey($this->onlyChannelId))->orderBy('id')->each(
            function (TelegramChannel $channel) use ($limit, $delay, &$seconds): void {
                ScanTelegramChannelJob::dispatch($channel->id, $limit, $this->trigger)
                    ->delay(now()->addSeconds($seconds));
                $seconds += $delay;
            }
        );
    }
}
