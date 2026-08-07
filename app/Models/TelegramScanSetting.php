<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramScanSetting extends Model
{
    protected $fillable = ['name', 'schedule_enabled', 'interval_minutes', 'channel_delay_seconds', 'scan_limit', 'with_comments', 'comments_limit', 'last_dispatched_at', 'notes'];

    protected function casts(): array
    {
        return ['schedule_enabled' => 'boolean', 'with_comments' => 'boolean', 'last_dispatched_at' => 'datetime'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['name' => 'default'], ['schedule_enabled' => false, 'interval_minutes' => 30, 'channel_delay_seconds' => 3, 'scan_limit' => 20, 'with_comments' => true, 'comments_limit' => 100, 'notes' => 'Telegram scanning is disabled by default.']);
    }

    public function limit(): int
    {
        return max(1, min(100, (int) $this->scan_limit));
    }

    public function interval(): int
    {
        return max(1, min(1440, (int) $this->interval_minutes));
    }
}
