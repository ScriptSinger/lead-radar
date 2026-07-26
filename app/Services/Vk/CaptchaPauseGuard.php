<?php

namespace App\Services\Vk;

use App\Models\ScanSetting;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Circuit-breaker for VK captcha / login / block storms.
 *
 * After N consecutive blocking failures, pauses automatic schedule for M minutes
 * (manual «Run scan now» still allowed). Does NOT bypass captcha.
 */
class CaptchaPauseGuard
{
    public const STREAK_CACHE_KEY = 'vk.captcha.fail_streak';

    public const NOTIFY_CACHE_KEY = 'vk.captcha.pause_notified';

    public function threshold(): int
    {
        return max(2, min(20, (int) config('services.vk.captcha_pause_threshold', 3)));
    }

    public function pauseMinutes(): int
    {
        return max(5, min(24 * 60, (int) config('services.vk.captcha_pause_minutes', 60)));
    }

    public function streak(): int
    {
        return max(0, (int) Cache::get(self::STREAK_CACHE_KEY, 0));
    }

    /**
     * Successful group scan — reset consecutive captcha counter.
     */
    public function recordSuccess(): void
    {
        if ($this->streak() > 0) {
            Log::info('vk.captcha.streak_reset', ['previous' => $this->streak()]);
        }
        Cache::forget(self::STREAK_CACHE_KEY);
    }

    /**
     * Permanent blocking failure (job exhausted retries / failed).
     *
     * @return array{streak: int, paused: bool, paused_until: string|null}
     */
    public function recordBlockingFailure(string $code, ?int $groupId = null): array
    {
        $streak = $this->streak() + 1;
        Cache::put(self::STREAK_CACHE_KEY, $streak, now()->addHours(12));

        Log::warning('vk.captcha.streak', [
            'streak' => $streak,
            'threshold' => $this->threshold(),
            'code' => $code,
            'group_id' => $groupId,
        ]);

        $paused = false;
        $pausedUntil = null;

        if ($streak >= $this->threshold()) {
            $paused = $this->activatePause($streak, $code, $groupId);
            $settings = ScanSetting::current()->fresh() ?? ScanSetting::current();
            $pausedUntil = $settings->paused_until?->toIso8601String();
        }

        return [
            'streak' => $streak,
            'paused' => $paused,
            'paused_until' => $pausedUntil,
        ];
    }

    /**
     * @return bool true if pause was newly applied
     */
    public function activatePause(int $streak, string $code, ?int $groupId = null): bool
    {
        $settings = ScanSetting::current();

        if ($settings->isCaptchaPaused()) {
            Log::debug('vk.captcha.pause_already_active', [
                'paused_until' => $settings->paused_until?->toIso8601String(),
            ]);

            return false;
        }

        $until = now()->addMinutes($this->pauseMinutes());
        $reason = sprintf(
            'Auto-pause after %dx %s (group_id=%s)',
            $streak,
            $code,
            $groupId ?? '—',
        );

        $settings->forceFill([
            'paused_until' => $until,
            'pause_reason' => mb_substr($reason, 0, 255),
        ])->save();

        // Reset streak so we don't re-trigger immediately after resume
        Cache::forget(self::STREAK_CACHE_KEY);

        Log::error('vk.captcha.schedule_paused', [
            'paused_until' => $until->toIso8601String(),
            'minutes' => $this->pauseMinutes(),
            'streak' => $streak,
            'code' => $code,
            'group_id' => $groupId,
        ]);

        $this->notifyTelegramOnce($until, $streak, $code);

        return true;
    }

    public function clearPause(?string $reason = null): void
    {
        $settings = ScanSetting::current();
        if ($settings->paused_until === null && $settings->pause_reason === null) {
            return;
        }

        $settings->forceFill([
            'paused_until' => null,
            'pause_reason' => null,
        ])->save();

        Cache::forget(self::STREAK_CACHE_KEY);
        Cache::forget(self::NOTIFY_CACHE_KEY);

        Log::info('vk.captcha.pause_cleared', ['reason' => $reason ?? 'manual']);
    }

    private function notifyTelegramOnce(\DateTimeInterface $until, int $streak, string $code): void
    {
        // One notify per pause window
        $key = self::NOTIFY_CACHE_KEY.':'.$until->getTimestamp();
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, $until);

        try {
            /** @var TelegramNotifier $notifier */
            $notifier = app(TelegramNotifier::class);
            if (! $notifier->enabled() || $notifier->isMuted()) {
                return;
            }

            $untilCarbon = \Carbon\Carbon::parse($until)
                ->timezone((string) config('app.timezone', 'UTC'))
                ->locale('ru');

            $untilLabel = $untilCarbon->format('H:i:s') . ' (' . $untilCarbon->diffForHumans() . ')';

            $notifier->sendMessage(implode("\n", [
                '⏸ <b>VK scans auto-paused</b>',
                "Reason: <code>{$code}</code> × {$streak}",
                "Until: <b>{$untilLabel}</b>",
                'Auto schedule is off until then. Manual «Run scan now» still works.',
                'Tip: raise group delay, turn comments off, wait, then clear pause in Scan Settings.',
            ]));
        } catch (Throwable $e) {
            Log::warning('vk.captcha.pause_notify_failed', ['error' => $e->getMessage()]);
        }
    }
}
