<?php

namespace App\Jobs;

use App\Models\VkGroup;
use App\Services\Vk\GroupScanner;
use App\Services\Vk\ParserClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scan one VK group (posts, optional comments, lead match).
 * Queue: vk.scan — long timeout for Playwright.
 */
class ScanVkGroupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $groupId,
        public int $limit = 6,
        public bool $withComments = true,
    ) {
        $this->onQueue('vk.scan');
    }

    public function handle(GroupScanner $scanner, ParserClient $parser): void
    {
        $group = VkGroup::query()->find($this->groupId);

        if ($group === null) {
            Log::warning('vk.scan.job.group_missing', ['group_id' => $this->groupId]);

            return;
        }

        if (! $group->active) {
            Log::info('vk.scan.job.group_inactive', ['group_id' => $this->groupId]);

            return;
        }

        if (! $parser->health()) {
            Log::warning('vk.scan.job.parser_down', ['group_id' => $this->groupId]);
            $this->release(60);

            return;
        }

        $stats = $scanner->scan(
            $group,
            max(1, min(30, $this->limit)),
            $this->withComments,
        );

        Log::info('vk.scan.job.done', [
            'group_id' => $this->groupId,
            'stats' => $stats,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('vk.scan.job.failed', [
            'group_id' => $this->groupId,
            'error' => $e?->getMessage(),
        ]);
    }
}
