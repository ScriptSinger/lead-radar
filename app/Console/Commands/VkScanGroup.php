<?php

namespace App\Console\Commands;

use App\Models\VkGroup;
use App\Services\Vk\GroupScanner;
use App\Services\Vk\ParserClient;
use App\Support\VkUrl;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('vk:scan {--group= : Scan a single group by id} {--limit=6 : Max posts per group (1-30)} {--with-comments : Also scrape comments for each post} {--queue : Dispatch to queue vk.scan instead of running sync}')]
#[Description('Scan active VK groups via parser and persist posts/comments')]
class VkScanGroup extends Command
{
    public function handle(GroupScanner $scanner, ParserClient $parser): int
    {
        $limit = max(1, min(30, (int) $this->option('limit')));
        $withComments = (bool) $this->option('with-comments');
        $groupId = $this->option('group');

        if ($this->option('queue')) {
            $this->call('vk:dispatch-scans', array_filter([
                '--group' => $groupId,
                '--limit' => $limit,
                '--with-comments' => $withComments ? '1' : '0',
            ], static fn ($v) => $v !== null && $v !== ''));

            return self::SUCCESS;
        }

        if (! $parser->health()) {
            $this->error('Parser is not healthy at '.config('services.parser.url'));

            return self::FAILURE;
        }

        $query = VkGroup::query()->where('active', true);

        if ($groupId !== null && $groupId !== '') {
            $query->whereKey((int) $groupId);
        }

        $groups = $query->orderBy('id')->get();

        if ($groups->isEmpty()) {
            $this->warn('No active groups to scan.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Scanning %d group(s), limit=%d, comments=%s',
            $groups->count(),
            $limit,
            $withComments ? 'yes' : 'no',
        ));

        $totals = [
            'posts_fetched' => 0,
            'posts_created' => 0,
            'posts_updated' => 0,
            'comments_fetched' => 0,
            'comments_created' => 0,
            'comments_updated' => 0,
            'leads_created' => 0,
            'leads_updated' => 0,
            'errors' => 0,
        ];

        $failedGroups = 0;

        foreach ($groups as $group) {
            $this->line('');
            $this->info("→ [{$group->id}] {$group->name} ({$group->url})");

            if (! VkUrl::isValid($group->url)) {
                $failedGroups++;
                $this->error('  skipped: '.VkUrl::validationMessage());

                continue;
            }

            try {
                $stats = $scanner->scan($group, $limit, $withComments, 'manual');
            } catch (Throwable $e) {
                $failedGroups++;
                $this->error("  failed: {$e->getMessage()}");

                continue;
            }

            $totals['posts_fetched'] += $stats['posts_fetched'];
            $totals['posts_created'] += $stats['posts_created'];
            $totals['posts_updated'] += $stats['posts_updated'];
            $totals['comments_fetched'] += $stats['comments_fetched'];
            $totals['comments_created'] += $stats['comments_created'];
            $totals['comments_updated'] += $stats['comments_updated'];
            $totals['leads_created'] += $stats['leads_created'] ?? 0;
            $totals['leads_updated'] += $stats['leads_updated'] ?? 0;
            $totals['errors'] += count($stats['errors']);

            $this->line(sprintf(
                '  posts: fetched=%d created=%d updated=%d',
                $stats['posts_fetched'],
                $stats['posts_created'],
                $stats['posts_updated'],
            ));

            if ($withComments) {
                $this->line(sprintf(
                    '  comments: fetched=%d created=%d updated=%d roots=%d nested=%d',
                    $stats['comments_fetched'],
                    $stats['comments_created'],
                    $stats['comments_updated'],
                    $stats['comments_roots'] ?? 0,
                    $stats['comments_nested'] ?? 0,
                ));
            }

            $this->line(sprintf(
                '  window: %s in=%d out=%d cutoff=%s',
                $stats['post_window'] ?? '—',
                $stats['posts_in_window'] ?? 0,
                $stats['posts_outside_window'] ?? 0,
                $stats['window_cutoff'] ?? '—',
            ));

            $this->line(sprintf(
                '  leads: created=%d updated=%d · %dms · run#%s',
                $stats['leads_created'] ?? 0,
                $stats['leads_updated'] ?? 0,
                $stats['duration_ms'] ?? 0,
                $stats['scan_run_id'] ?? '—',
            ));

            if ($stats['errors'] !== []) {
                foreach ($stats['errors'] as $error) {
                    $this->warn("  ! {$error}");
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. posts +%d/~%d, comments +%d/~%d, leads +%d/~%d, group failures=%d, row errors=%d',
            $totals['posts_created'],
            $totals['posts_updated'],
            $totals['comments_created'],
            $totals['comments_updated'],
            $totals['leads_created'],
            $totals['leads_updated'],
            $failedGroups,
            $totals['errors'],
        ));

        return $failedGroups > 0 ? self::FAILURE : self::SUCCESS;
    }
}
