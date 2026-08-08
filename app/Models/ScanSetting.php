<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\PostWindow;
use App\Support\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Singleton-ish scan policy (row name=default).
 * Edited in MoonShine; used by scheduler tick + dispatch/scanner.
 */
class ScanSetting extends Model
{
    use BelongsToWorkspace;

    public const CACHE_KEY = 'scan_settings.current.';

    public const CACHE_TTL_SECONDS = 30;

    public const NAME_DEFAULT = 'default';

    protected $fillable = [
        'name',
        'schedule_enabled',
        'interval_minutes',
        'group_delay_seconds',
        'scan_limit',
        'with_comments',
        'post_window',
        'last_dispatched_at',
        'notes',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_enabled' => 'boolean',
            'with_comments' => 'boolean',
            'interval_minutes' => 'integer',
            'group_delay_seconds' => 'integer',
            'scan_limit' => 'integer',
            'last_dispatched_at' => 'datetime',
        ];
    }

    /**
     * Defaults used by seeder and firstOrCreate.
     *
     * Conservative first-run baseline: configured but disabled until an
     * operator configures the VK API token and enables the schedule.
     *
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'name' => self::NAME_DEFAULT,
            'schedule_enabled' => false,
            'interval_minutes' => 30,
            'group_delay_seconds' => 50,
            'scan_limit' => 8,
            'with_comments' => true,
            'post_window' => PostWindow::MODE_SINCE_LAST_SCAN,
            'last_dispatched_at' => null,
            'notes' => 'Automatic scanning is off by default. After configuring VK_API_TOKEN, enable it in MoonShine or Telegram. Policy: every 30 minutes, 50s between groups, last 8 posts, comments on, match since last scan.',
        ];
    }

    public static function current(?int $workspaceId = null): self
    {
        $workspaceId ??= WorkspaceContext::id();

        if ($workspaceId === null) {
            throw new \LogicException('A workspace must be selected to resolve scan settings.');
        }

        $resolve = static function () use ($workspaceId): self {
            return self::query()->firstOrCreate(
                ['workspace_id' => $workspaceId, 'name' => self::NAME_DEFAULT],
                self::defaultAttributes(),
            );
        };

        // Avoid cache entirely in tests (array/file leftovers / incomplete class).
        if (app()->runningUnitTests()) {
            return $resolve();
        }

        // Cache only the id — never the Eloquent instance.
        $cacheKey = self::CACHE_KEY.$workspaceId;
        $cached = Cache::get($cacheKey);
        $id = is_numeric($cached) ? (int) $cached : null;

        if ($id === null) {
            $model = $resolve();
            Cache::put($cacheKey, $model->id, self::CACHE_TTL_SECONDS);

            return $model;
        }

        $model = self::query()->find($id);

        if ($model instanceof self) {
            return $model;
        }

        self::forgetCache($workspaceId);

        return $resolve();
    }

    public static function forgetCache(?int $workspaceId = null): void
    {
        $workspaceId ??= WorkspaceContext::id();

        if ($workspaceId !== null) {
            Cache::forget(self::CACHE_KEY.$workspaceId);
        }
    }

    protected static function booted(): void
    {
        static::saved(static fn (self $setting) => self::forgetCache($setting->workspace_id));
        static::deleted(static fn (self $setting) => self::forgetCache($setting->workspace_id));
    }

    public function normalizedLimit(): int
    {
        return max(1, min(30, (int) $this->scan_limit));
    }

    public function normalizedIntervalMinutes(): int
    {
        // 5 min floor keeps scheduler useful; 24h ceiling for "almost off but enabled"
        return max(5, min(24 * 60, (int) $this->interval_minutes));
    }

    public function normalizedGroupDelaySeconds(): int
    {
        return max(0, min(600, (int) $this->group_delay_seconds));
    }

    public function normalizedPostWindow(): string
    {
        return PostWindow::mode($this->post_window);
    }

    /**
     * Whether the schedule tick should dispatch a new fan-out now.
     */
    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $now = $now ? Carbon::parse($now) : now();

        if ($this->last_dispatched_at === null) {
            return true;
        }

        $next = $this->last_dispatched_at->copy()->addMinutes($this->normalizedIntervalMinutes());

        return $now->greaterThanOrEqualTo($next);
    }

    public function markDispatched(): void
    {
        $this->forceFill(['last_dispatched_at' => now()])->save();
    }

    /**
     * Estimate fan-out length for admin UI (rough).
     */
    public function estimatedWaveSeconds(int $activeGroupCount): int
    {
        $count = max(0, $activeGroupCount);
        if ($count === 0) {
            return 0;
        }

        // delay between jobs + ~45s average scrape without heavy comments bias
        $perGroup = $this->normalizedGroupDelaySeconds() + ($this->with_comments ? 90 : 45);

        return ($count - 1) * $this->normalizedGroupDelaySeconds() + $perGroup;
    }

    public function logSnapshot(string $event, array $extra = []): void
    {
        Log::info($event, array_merge([
            'schedule_enabled' => $this->schedule_enabled,
            'interval_minutes' => $this->normalizedIntervalMinutes(),
            'group_delay_seconds' => $this->normalizedGroupDelaySeconds(),
            'scan_limit' => $this->normalizedLimit(),
            'with_comments' => $this->with_comments,
            'post_window' => $this->normalizedPostWindow(),
            'last_dispatched_at' => $this->last_dispatched_at?->toIso8601String(),
        ], $extra));
    }
}
