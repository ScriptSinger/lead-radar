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

        foreach ($groups as $group) {
            ScanVkGroupJob::dispatch(
                $group->id,
                $this->limit,
                $this->withComments,
            )->delay(now()->addSeconds($delay));

            $dispatched++;
            $delay += $delaySeconds;
        }

        Log::info('vk.scan.dispatch_done', [
            'dispatched' => $dispatched,
            'delay_step' => $delaySeconds,
            'with_comments' => $this->withComments,
            'limit' => $this->limit,
        ]);
    }
}
