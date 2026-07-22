<?php

namespace App\Jobs;

use App\Exceptions\ParserUnavailableException;
use App\Models\VkGroup;
use App\Services\Telegram\TelegramNotifier;
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

    public int $tries = 3;

    public int $timeout = 300;

    public int $maxExceptions = 2;

    /** @return list<int> seconds */
    public function backoff(): array
    {
        return [30, 90, 180];
    }

    /** Default on property so older queued payloads without this field still run. */
    public string $trigger = 'job';

    public function __construct(
        public int $groupId,
        public int $limit = 6,
        public bool $withComments = true,
        ?string $trigger = null,
    ) {
        if ($trigger !== null) {
            $this->trigger = $trigger;
        }

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

        // Pre-check without creating a failed run if parser is flapping
        if (! $parser->health()) {
            Log::warning('vk.scan.job.parser_down_precheck', [
                'group_id' => $this->groupId,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60);

                return;
            }

            throw new ParserUnavailableException(
                'Parser unavailable after '.$this->attempts().' attempts'
            );
        }

        $stats = $scanner->scan(
            $group,
            max(1, min(30, $this->limit)),
            $this->withComments,
            $this->trigger,
        );

        Log::info('vk.scan.job.done', [
            'group_id' => $this->groupId,
            'scan_run_id' => $stats['scan_run_id'] ?? null,
            'duration_ms' => $stats['duration_ms'] ?? null,
            'posts_fetched' => $stats['posts_fetched'] ?? 0,
            'leads_created' => $stats['leads_created'] ?? 0,
            'error_count' => count($stats['errors'] ?? []),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('vk.scan.job.failed', [
            'group_id' => $this->groupId,
            'error' => $e?->getMessage(),
        ]);

        $this->alertTelegram($e);
    }

    private function alertTelegram(?Throwable $e): void
    {
        try {
            if (! config('services.telegram.notify_enabled', true)) {
                return;
            }

            /** @var TelegramNotifier $notifier */
            $notifier = app(TelegramNotifier::class);

            if (! $notifier->enabled() || $notifier->isMuted()) {
                return;
            }

            $group = VkGroup::query()->find($this->groupId);
            $name = $group?->name ?? ('#'.$this->groupId);
            $msg = $e?->getMessage() ?? 'unknown error';

            $notifier->sendMessage(implode("\n", [
                '🚨 <b>VK scan job failed</b>',
                "👥 {$name}",
                '❌ '.e(mb_substr($msg, 0, 400)),
            ]));
        } catch (Throwable $alertError) {
            Log::warning('vk.scan.job.alert_failed', [
                'error' => $alertError->getMessage(),
            ]);
        }
    }
}
