<?php

namespace App\Jobs;

use App\Models\TelegramChannel;
use App\Models\TelegramScanSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchTelegramChannelScansJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $limit = null, public ?int $onlyChannelId = null, public string $trigger = 'schedule', public ?int $workspaceId = null)
    {
        $this->onConnection('redis')->onQueue('telegram.scan');
    }

    public function handle(): void
    {
        $workspaceId = $this->workspaceId
            ?? ($this->onlyChannelId !== null ? TelegramChannel::query()->whereKey($this->onlyChannelId)->value('workspace_id') : null)
            ?? TelegramScanSetting::query()->value('workspace_id');

        if ($workspaceId === null) {
            return;
        }

        $settings = TelegramScanSetting::current((int) $workspaceId);
        $limit = max(1, min(100, $this->limit ?? $settings->limit()));
        $delay = max(0, (int) $settings->channel_delay_seconds);
        $seconds = 0;
        TelegramChannel::query()->where('workspace_id', $workspaceId)->where('active', true)->when($this->onlyChannelId, fn ($q) => $q->whereKey($this->onlyChannelId))->orderBy('id')->each(
            function (TelegramChannel $channel) use ($limit, $delay, $settings, &$seconds): void {
                ScanTelegramChannelJob::dispatch($channel->id, $limit, $this->trigger, (bool) $settings->with_comments, (int) $settings->comments_limit)
                    ->delay(now()->addSeconds($seconds));
                $seconds += $delay;
            }
        );
    }
}
