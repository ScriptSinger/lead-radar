<?php

namespace App\Console\Commands;

use App\Jobs\DispatchTelegramChannelScansJob;
use App\Models\TelegramChannel;
use App\Services\Telegram\TelegramChannelScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:dispatch-scans {--channel= : Only this channel id} {--limit=20 : Posts per channel (1-100)} {--sync : Run in process instead of Redis queue}')]
#[Description('Queue Telegram channel scans on Redis queue telegram.scan')]
class TelegramDispatchScans extends Command
{
    public function handle(TelegramChannelScanner $scanner): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $id = $this->option('channel');
        $onlyChannelId = $id !== null && $id !== '' ? (int) $id : null;
        if ($this->option('sync')) {
            $channels = TelegramChannel::query()->where('active', true)->when($onlyChannelId, fn ($q) => $q->whereKey($onlyChannelId))->get();
            foreach ($channels as $channel) { $scanner->scan($channel, $limit); }
            $this->info('Telegram scans finished.');
            return self::SUCCESS;
        }
        DispatchTelegramChannelScansJob::dispatch($limit, $onlyChannelId, 'manual');
        $this->info('Queued Telegram scans for '.TelegramChannel::query()->where('active', true)->when($onlyChannelId, fn ($q) => $q->whereKey($onlyChannelId))->count().' channel(s) on redis:telegram.scan.');
        return self::SUCCESS;
    }
}
