<?php

namespace App\Jobs;

use App\Models\VkGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fan-out: enqueue ScanVkGroupJob per active group with staggered delay (rate limit).
 */
class DispatchVkGroupScansJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $limit = 6,
        public bool $withComments = true,
        public ?int $onlyGroupId = null,
        public string $trigger = 'schedule',
    ) {
        $this->onQueue('vk.scan');
    }

    public function handle(): void
    {
        $query = VkGroup::query()
            ->where('active', true)
            ->orderBy('id');

        if ($this->onlyGroupId !== null) {
            $query->whereKey($this->onlyGroupId);
        }

        $groups = $query->get();
        $delaySeconds = max(0, (int) config('services.vk.scan_group_delay_seconds', 45));
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
                $this->limit,
                $this->withComments,
                $this->trigger,
            )->delay(now()->addSeconds($delay));

            $dispatched++;
            $delay += $delaySeconds;
        }

        Log::info('vk.scan.dispatch_done', [
            'dispatched' => $dispatched,
            'skipped_invalid_url' => $skipped,
            'delay_step' => $delaySeconds,
            'with_comments' => $this->withComments,
            'limit' => $this->limit,
            'trigger' => $this->trigger,
        ]);
    }
}
