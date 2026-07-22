<?php

namespace App\Support;

use App\Models\VkGroup;
use App\Models\VkPost;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Time window for which scraped posts get comments + lead matching.
 *
 * Modes (config services.vk.post_window):
 * - today            — calendar day in app timezone
 * - since_last_scan  — posted_at >= last_scan_at; first scan falls back to today
 * - all              — no date filter (every post returned by parser limit)
 */
class PostWindow
{
    public const MODE_TODAY = 'today';

    public const MODE_SINCE_LAST_SCAN = 'since_last_scan';

    public const MODE_ALL = 'all';

    public static function mode(?string $override = null): string
    {
        $mode = strtolower(trim((string) ($override ?? config('services.vk.post_window', self::MODE_SINCE_LAST_SCAN))));

        return match ($mode) {
            self::MODE_TODAY, 'day' => self::MODE_TODAY,
            self::MODE_ALL, 'none', 'off' => self::MODE_ALL,
            default => self::MODE_SINCE_LAST_SCAN,
        };
    }

    /**
     * Lower bound for posted_at (inclusive), or null if no filter.
     */
    public static function cutoff(VkGroup $group, ?string $mode = null): ?CarbonInterface
    {
        $mode = self::mode($mode);

        if ($mode === self::MODE_ALL) {
            return null;
        }

        if ($mode === self::MODE_TODAY) {
            return now()->startOfDay();
        }

        // since_last_scan
        if ($group->last_scan_at !== null) {
            return $group->last_scan_at->copy();
        }

        // First scan: only "today" so we don't treat ancient wall posts as fresh leads
        return now()->startOfDay();
    }

    public static function includes(?CarbonInterface $postedAt, ?CarbonInterface $cutoff): bool
    {
        if ($cutoff === null) {
            return true;
        }

        if ($postedAt === null) {
            // Unknown date: keep post so we don't silently drop content
            return true;
        }

        return $postedAt->greaterThanOrEqualTo($cutoff);
    }

    public static function postInWindow(VkPost $post, ?CarbonInterface $cutoff): bool
    {
        $postedAt = $post->posted_at;

        if ($postedAt instanceof CarbonInterface) {
            return self::includes($postedAt, $cutoff);
        }

        if (is_string($postedAt) && $postedAt !== '') {
            try {
                return self::includes(Carbon::parse($postedAt), $cutoff);
            } catch (\Throwable) {
                return true;
            }
        }

        return self::includes(null, $cutoff);
    }
}
