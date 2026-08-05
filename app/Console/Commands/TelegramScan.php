<?php
namespace App\Console\Commands;
use App\Models\TelegramChannel; use App\Services\Telegram\TelegramChannelScanner; use Illuminate\Console\Command;
class TelegramScan extends Command {
    protected $signature = 'telegram:scan {--channel=} {--limit=20} {--queue}';
    protected $description = 'Scan active Telegram channels and persist posts/leads';
    public function handle(TelegramChannelScanner $scanner): int {
        if ($this->option('queue')) return $this->call('telegram:dispatch-scans', array_filter(['--channel'=>$this->option('channel'),'--limit'=>$this->option('limit')]));
        $channels=TelegramChannel::query()->where('active',true)->when($this->option('channel'),fn($q,$id)=>$q->whereKey((int)$id))->get();
        foreach($channels as $channel){$s=$scanner->scan($channel,max(1,min(100,(int)$this->option('limit'))));$this->info("#{$channel->id}: posts {$s['posts_created']} new, leads {$s['leads_created']} new");}
        return self::SUCCESS;
    }
}
