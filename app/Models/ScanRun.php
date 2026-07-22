<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARSER_DOWN = 'parser_down';

    protected $fillable = [
        'group_id',
        'trigger',
        'status',
        'with_comments',
        'limit',
        'posts_fetched',
        'posts_created',
        'posts_updated',
        'comments_fetched',
        'comments_created',
        'comments_updated',
        'leads_created',
        'leads_updated',
        'error_count',
        'errors',
        'stats',
        'error_message',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'with_comments' => 'boolean',
            'errors' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VkGroup::class, 'group_id');
    }

    public static function start(
        VkGroup $group,
        string $trigger,
        int $limit,
        bool $withComments,
    ): self {
        return self::query()->create([
            'group_id' => $group->id,
            'trigger' => $trigger,
            'status' => self::STATUS_RUNNING,
            'with_comments' => $withComments,
            'limit' => $limit,
            'started_at' => now(),
        ]);
    }

    public function markSuccess(array $stats, int $durationMs): void
    {
        $errors = $stats['errors'] ?? [];

        $this->fill([
            'status' => self::STATUS_SUCCESS,
            'posts_fetched' => $stats['posts_fetched'] ?? 0,
            'posts_created' => $stats['posts_created'] ?? 0,
            'posts_updated' => $stats['posts_updated'] ?? 0,
            'comments_fetched' => $stats['comments_fetched'] ?? 0,
            'comments_created' => $stats['comments_created'] ?? 0,
            'comments_updated' => $stats['comments_updated'] ?? 0,
            'leads_created' => $stats['leads_created'] ?? 0,
            'leads_updated' => $stats['leads_updated'] ?? 0,
            'error_count' => count($errors),
            'errors' => $errors !== [] ? array_values($errors) : null,
            'stats' => $stats,
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(
        string $message,
        int $durationMs,
        string $status = self::STATUS_FAILED,
        array $stats = [],
    ): void {
        $errors = $stats['errors'] ?? [];

        $this->fill([
            'status' => $status,
            'posts_fetched' => $stats['posts_fetched'] ?? 0,
            'posts_created' => $stats['posts_created'] ?? 0,
            'posts_updated' => $stats['posts_updated'] ?? 0,
            'comments_fetched' => $stats['comments_fetched'] ?? 0,
            'comments_created' => $stats['comments_created'] ?? 0,
            'comments_updated' => $stats['comments_updated'] ?? 0,
            'leads_created' => $stats['leads_created'] ?? 0,
            'leads_updated' => $stats['leads_updated'] ?? 0,
            'error_count' => max(1, count($errors)),
            'errors' => $errors !== [] ? array_values($errors) : [$message],
            'stats' => $stats !== [] ? $stats : null,
            'error_message' => mb_substr($message, 0, 2000),
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ])->save();
    }
}
