<?php

namespace App\Jobs;

use App\Models\ScanSetting;
use App\Models\VkGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fan-out: enqueue ScanVkGroupJob per active group with staggered delay (rate limit).
 * Delay / defaults come from scan_settings (MoonShine) when not overridden by constructor.
 */
class DispatchVkGroupScansJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /** Default on property so older queued payloads without this field still run. */
    public string $trigger = 'schedule';

    public function __construct(
        public ?int $limit = null,
        public ?bool $withComments = null,
        public ?int $onlyGroupId = null,
        ?string $trigger = null,
    ) {
        if ($trigger !== null) {
            $this->trigger = $trigger;
        }

        $this->onQueue('vk.scan');
    }

    public function handle(): void
    {
        $settings = ScanSetting::current();
        $limit = max(1, min(30, $this->limit ?? $settings->normalizedLimit()));
        $withComments = $this->withComments ?? (bool) $settings->with_comments;
        $delaySeconds = $settings->normalizedGroupDelaySeconds();
        $trigger = $this->trigger ?: 'schedule';

        $query = VkGroup::query()
            ->where('active', true)
            ->orderBy('id');

        if ($this->onlyGroupId !== null) {
            $query->whereKey($this->onlyGroupId);
        }

        $groups = $query->get();
        $delay = 0;
        $dispatched = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            if (! \App\Support\VkUrl::isValid($group->url)) {
                Log::warning('vk.scan.dispatch.invalid_url', [
                    'group_id' => $group->id,
                    'url' => $group->url,
                ]);
                $skipped++;

                continue;
            }

            ScanVkGroupJob::dispatch(
                $group->id,
                $limit,
                $withComments,
                $trigger,
            )->delay(now()->addSeconds($delay));

            $dispatched++;
            $delay += $delaySeconds;
        }

        Log::info('vk.scan.dispatch_done', [
            'dispatched' => $dispatched,
            'skipped_invalid_url' => $skipped,
            'delay_step' => $delaySeconds,
            'with_comments' => $withComments,
            'limit' => $limit,
            'post_window' => $settings->normalizedPostWindow(),
            'trigger' => $trigger,
        ]);
    }
}
