<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;

class TelegramScanSetting extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['name', 'schedule_enabled', 'interval_minutes', 'channel_delay_seconds', 'scan_limit', 'with_comments', 'comments_limit', 'last_dispatched_at', 'notes', 'workspace_id'];

    protected function casts(): array
    {
        return ['schedule_enabled' => 'boolean', 'with_comments' => 'boolean', 'last_dispatched_at' => 'datetime'];
    }

    public static function current(?int $workspaceId = null): self
    {
        $workspaceId ??= WorkspaceContext::id();

        if ($workspaceId === null) {
            throw new \LogicException('A workspace must be selected to resolve Telegram scan settings.');
        }

        return self::query()->firstOrCreate(['workspace_id' => $workspaceId, 'name' => 'default'], ['schedule_enabled' => false, 'interval_minutes' => 30, 'channel_delay_seconds' => 3, 'scan_limit' => 20, 'with_comments' => true, 'comments_limit' => 100, 'notes' => 'Telegram scanning is disabled by default.']);
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
