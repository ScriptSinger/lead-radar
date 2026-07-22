<?php

namespace App\Console\Commands;

use App\Jobs\DispatchVkGroupScansJob;
use App\Jobs\ScanVkGroupJob;
use App\Models\VkGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vk:dispatch-scans {--group= : Only this group id} {--limit=6 : Posts per group} {--with-comments=1 : Scrape comments} {--sync : Run in process instead of queue}')]
#[Description('Queue (or sync) VK group scans with rate-limited fan-out')]
class VkDispatchScans extends Command
{
    public function handle(): int
    {
        $limit = max(1, min(30, (int) $this->option('limit')));
        $withComments = filter_var($this->option('with-comments'), FILTER_VALIDATE_BOOL);
        $groupId = $this->option('group');
        $onlyGroupId = ($groupId !== null && $groupId !== '') ? (int) $groupId : null;
        $sync = (bool) $this->option('sync');

        if ($sync) {
            return $this->runSync($onlyGroupId, $limit, $withComments);
        }

        DispatchVkGroupScansJob::dispatch($limit, $withComments, $onlyGroupId);

        $count = VkGroup::query()
            ->where('active', true)
            ->when($onlyGroupId, fn ($q) => $q->whereKey($onlyGroupId))
            ->count();

        $delay = (int) config('services.vk.scan_group_delay_seconds', 45);

        $this->info(sprintf(
            'Queued scan fan-out for %d group(s) on queue=vk.scan (delay step %ds, comments=%s, limit=%d)',
            $count,
            $delay,
            $withComments ? 'yes' : 'no',
            $limit,
        ));

        return self::SUCCESS;
    }

    private function runSync(?int $onlyGroupId, int $limit, bool $withComments): int
    {
        $query = VkGroup::query()->where('active', true)->orderBy('id');

        if ($onlyGroupId !== null) {
            $query->whereKey($onlyGroupId);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->warn('No active groups.');

            return self::SUCCESS;
        }

        foreach ($groups as $group) {
            $this->info("Sync scan group #{$group->id} {$group->name}");
            // Run job handle inline without queue
            (new ScanVkGroupJob($group->id, $limit, $withComments, 'manual'))
                ->handle(app(\App\Services\Vk\GroupScanner::class), app(\App\Services\Vk\ParserClient::class));
        }

        $this->info('Sync scans finished.');

        return self::SUCCESS;
    }
}
