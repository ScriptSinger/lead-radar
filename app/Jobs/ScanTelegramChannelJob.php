<?php

namespace App\Jobs;

use App\Models\TelegramChannel;
use App\Services\Telegram\TelegramChannelScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ScanTelegramChannelJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $channelId, public int $limit = 20, public string $trigger = 'job', public bool $withComments = true, public int $commentsLimit = 100)
    {
        $this->onConnection('redis')->onQueue('telegram.scan');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("telegram-scan-channel:{$this->channelId}"))->releaseAfter(30)->expireAfter(240)];
    }

    public function backoff(): array
    {
        return [30, 90, 180];
    }

    public function handle(TelegramChannelScanner $scanner): void
    {
        $channel = TelegramChannel::query()->find($this->channelId);
        if ($channel?->active) {
            $scanner->scan($channel, $this->limit, $this->trigger, $this->withComments, $this->commentsLimit);
        }
    }
}
