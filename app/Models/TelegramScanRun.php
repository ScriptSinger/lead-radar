<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramScanRun extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'channel_id',
        'trigger',
        'status',
        'limit',
        'posts_fetched',
        'posts_created',
        'posts_updated',
        'leads_created',
        'leads_updated',
        'error_count',
        'errors',
        'stats',
        'error_message',
        'duration_ms',
        'started_at',
        'finished_at',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TelegramChannel::class, 'channel_id');
    }

    public static function start(TelegramChannel $channel, string $trigger, int $limit): self
    {
        return self::query()->create([
            'workspace_id' => $channel->workspace_id,
            'channel_id' => $channel->id,
            'trigger' => $trigger,
            'status' => ScanStatus::RUNNING->value,
            'limit' => $limit,
            'started_at' => now(),
        ]);
    }

    public function markSuccess(array $stats, int $durationMs): void
    {
        $errors = $stats['errors'] ?? [];
        $this->fill([
            'status' => ScanStatus::SUCCESS->value,
            'posts_fetched' => $stats['posts_fetched'] ?? 0,
            'posts_created' => $stats['posts_created'] ?? 0,
            'posts_updated' => $stats['posts_updated'] ?? 0,
            'error_count' => count($errors),
            'errors' => $errors ?: null,
            'stats' => $stats,
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(string $message, int $durationMs, array $stats = []): void
    {
        $errors = $stats['errors'] ?? [];
        $this->fill([
            'status' => ScanStatus::FAILED->value,
            'posts_fetched' => $stats['posts_fetched'] ?? 0,
            'posts_created' => $stats['posts_created'] ?? 0,
            'posts_updated' => $stats['posts_updated'] ?? 0,
            'error_count' => max(1, count($errors)),
            'errors' => $errors ?: [$message],
            'stats' => $stats ?: null,
            'error_message' => mb_substr($message, 0, 2000),
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ])->save();
    }
}
