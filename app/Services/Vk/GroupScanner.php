<?php

namespace App\Services\Vk;

use App\Exceptions\ParserUnavailableException;
use App\Models\ScanRun;
use App\Models\ScanSetting;
use App\Models\VkComment;
use App\Models\VkGroup;
use App\Models\VkPost;
use App\Support\PostWindow;
use App\Support\VkUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroupScanner
{
    public function __construct(
        private readonly ParserClient $parser,
        private readonly CommentTreeResolver $treeResolver,
        private readonly LeadMatcher $leadMatcher,
    ) {}

    /**
     * Scan a single active group: fetch posts (and optionally comments), upsert, match leads.
     *
     * @return array{
     *     group_id: int,
     *     posts_fetched: int,
     *     posts_created: int,
     *     posts_updated: int,
     *     comments_fetched: int,
     *     comments_created: int,
     *     comments_updated: int,
     *     comments_roots: int,
     *     comments_nested: int,
     *     leads_created: int,
     *     leads_updated: int,
     *     posts_in_window: int,
     *     posts_outside_window: int,
     *     post_window: string,
     *     window_cutoff: string|null,
     *     errors: list<string>,
     *     duration_ms: int,
     *     scan_run_id: int|null
     * }
     */
    public function scan(
        VkGroup $group,
        int $limit = 6,
        bool $withComments = false,
        string $trigger = 'manual',
        ?string $postWindow = null,
    ): array {
        $startedAt = microtime(true);
        $run = ScanRun::start($group, $trigger, $limit, $withComments);

        $windowMode = PostWindow::mode(
            $postWindow ?? ScanSetting::current()->normalizedPostWindow()
        );
        // Capture cutoff before last_scan_at is updated at the end of a successful run
        $windowCutoff = PostWindow::cutoff($group, $windowMode);

        $stats = [
            'group_id' => $group->id,
            'posts_fetched' => 0,
            'posts_created' => 0,
            'posts_updated' => 0,
            'comments_fetched' => 0,
            'comments_created' => 0,
            'comments_updated' => 0,
            'comments_roots' => 0,
            'comments_nested' => 0,
            'leads_created' => 0,
            'leads_updated' => 0,
            'posts_in_window' => 0,
            'posts_outside_window' => 0,
            'post_window' => $windowMode,
            'window_cutoff' => $windowCutoff?->toIso8601String(),
            'errors' => [],
            'duration_ms' => 0,
            'scan_run_id' => $run->id,
        ];

        Log::info('vk.scan.started', [
            'scan_run_id' => $run->id,
            'group_id' => $group->id,
            'group' => $group->name,
            'url' => $group->url,
            'limit' => $limit,
            'with_comments' => $withComments,
            'trigger' => $trigger,
            'post_window' => $windowMode,
            'window_cutoff' => $stats['window_cutoff'],
        ]);

        try {
            if (! VkUrl::isValid($group->url)) {
                throw new \InvalidArgumentException(VkUrl::validationMessage().' Got: '.$group->url);
            }

            if (! $this->parser->health()) {
                throw new ParserUnavailableException(
                    'Parser is not healthy at '.config('services.parser.url')
                );
            }

            $rawPosts = $this->parser->scrapeGroup($group->url, $limit);
            $stats['posts_fetched'] = count($rawPosts);

            if ($stats['posts_fetched'] === 0) {
                Log::warning('vk.scan.empty_posts', [
                    'scan_run_id' => $run->id,
                    'group_id' => $group->id,
                    'url' => $group->url,
                ]);
            }

            /** @var list<VkPost> $savedPosts */
            $savedPosts = [];
            /** @var list<VkPost> $windowPosts posts for comments + lead match */
            $windowPosts = [];

            foreach ($rawPosts as $raw) {
                try {
                    [$post, $created] = $this->upsertPost($group, $raw);
                    $savedPosts[] = $post;

                    if ($created) {
                        $stats['posts_created']++;
                    } else {
                        $stats['posts_updated']++;
                    }

                    if (PostWindow::postInWindow($post, $windowCutoff)) {
                        $windowPosts[] = $post;
                        $stats['posts_in_window']++;
                    } else {
                        $stats['posts_outside_window']++;
                    }
                } catch (Throwable $e) {
                    $message = "post upsert failed: {$e->getMessage()}";
                    $stats['errors'][] = $message;
                    Log::warning('vk.scan.post_failed', [
                        'scan_run_id' => $run->id,
                        'group_id' => $group->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($withComments) {
                foreach ($windowPosts as $post) {
                    if (! $post->url) {
                        continue;
                    }

                    try {
                        $commentStats = $this->scanCommentsForPost($post);
                        $stats['comments_fetched'] += $commentStats['fetched'];
                        $stats['comments_created'] += $commentStats['created'];
                        $stats['comments_updated'] += $commentStats['updated'];
                        $stats['comments_roots'] += $commentStats['roots'];
                        $stats['comments_nested'] += $commentStats['nested'];
                        foreach ($commentStats['errors'] as $err) {
                            $stats['errors'][] = $err;
                        }
                    } catch (Throwable $e) {
                        $message = "comments for post {$post->vk_post_id}: {$e->getMessage()}";
                        $stats['errors'][] = $message;
                        Log::warning('vk.scan.comments_failed', [
                            'scan_run_id' => $run->id,
                            'group_id' => $group->id,
                            'post_id' => $post->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            try {
                // Match only posts inside the time window from this scrape.
                // Full rematch of historical content: php artisan vk:match-leads
                $postsToMatch = collect($windowPosts)
                    ->unique('id')
                    ->values()
                    ->each(static function (VkPost $post): void {
                        $post->loadMissing('comments');
                    });

                $leadStats = $this->leadMatcher->matchPosts($postsToMatch, withComments: true);
                $stats['leads_created'] += $leadStats['created'];
                $stats['leads_updated'] += $leadStats['updated'];
            } catch (Throwable $e) {
                $stats['errors'][] = "lead match group {$group->id}: {$e->getMessage()}";
                Log::warning('vk.scan.lead_match_failed', [
                    'scan_run_id' => $run->id,
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $group->forceFill(['last_scan_at' => now()])->save();

            $stats['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $run->markSuccess($stats, $stats['duration_ms']);

            Log::info('vk.scan.finished', [
                'scan_run_id' => $run->id,
                'group_id' => $group->id,
                'group' => $group->name,
                'duration_ms' => $stats['duration_ms'],
                'posts_fetched' => $stats['posts_fetched'],
                'posts_in_window' => $stats['posts_in_window'],
                'posts_outside_window' => $stats['posts_outside_window'],
                'post_window' => $windowMode,
                'posts_created' => $stats['posts_created'],
                'posts_updated' => $stats['posts_updated'],
                'comments_fetched' => $stats['comments_fetched'],
                'leads_created' => $stats['leads_created'],
                'error_count' => count($stats['errors']),
            ]);

            return $stats;
        } catch (ParserUnavailableException $e) {
            $stats['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $stats['errors'][] = $e->getMessage();
            $run->markFailed(
                $e->getMessage(),
                $stats['duration_ms'],
                ScanRun::STATUS_PARSER_DOWN,
                $stats,
            );

            Log::error('vk.scan.parser_down', [
                'scan_run_id' => $run->id,
                'group_id' => $group->id,
                'duration_ms' => $stats['duration_ms'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $stats['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $stats['errors'][] = $e->getMessage();
            $run->markFailed($e->getMessage(), $stats['duration_ms'], ScanRun::STATUS_FAILED, $stats);

            Log::error('vk.scan.failed', [
                'scan_run_id' => $run->id,
                'group_id' => $group->id,
                'duration_ms' => $stats['duration_ms'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{0: VkPost, 1: bool}
     */
    private function upsertPost(VkGroup $group, array $raw): array
    {
        $vkPostId = trim((string) ($raw['vk_post_id'] ?? ''));

        if ($vkPostId === '') {
            throw new \InvalidArgumentException('vk_post_id is empty');
        }

        $url = trim((string) ($raw['url'] ?? ''));
        if ($url === '') {
            $url = 'https://vk.com/wall'.$vkPostId;
        }

        $attributes = [
            'group_id' => $group->id,
            'text' => $raw['text'] ?? null,
            'url' => $url,
            'author_id' => $this->nullableInt($raw['author_id'] ?? null),
            'posted_at' => $this->resolvePostedAt(
                $raw['posted_at'] ?? null,
                $raw['posted_at_raw'] ?? null,
            ),
        ];

        $existing = VkPost::query()->where('vk_post_id', $vkPostId)->first();

        if ($existing) {
            $existing->fill($attributes)->save();

            return [$existing, false];
        }

        $post = VkPost::query()->create([
            'vk_post_id' => $vkPostId,
            ...$attributes,
        ]);

        return [$post, true];
    }

    /**
     * @return array{
     *     fetched: int,
     *     created: int,
     *     updated: int,
     *     roots: int,
     *     nested: int,
     *     errors: list<string>
     * }
     */
    private function scanCommentsForPost(VkPost $post): array
    {
        $result = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'roots' => 0,
            'nested' => 0,
            'errors' => [],
        ];

        $rawComments = $this->parser->scrapeComments($post->url);
        $result['fetched'] = count($rawComments);

        foreach ($rawComments as $raw) {
            try {
                $created = $this->upsertComment($post, $raw);
                if ($created) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (Throwable $e) {
                $result['errors'][] = "comment upsert: {$e->getMessage()}";
            }
        }

        $tree = $this->treeResolver->resolveForPost($post);
        $result['roots'] = $tree['roots'];
        $result['nested'] = $tree['nested'];

        return $result;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function upsertComment(VkPost $post, array $raw): bool
    {
        $vkCommentId = $this->nullableInt($raw['vk_comment_id'] ?? null);

        if ($vkCommentId === null) {
            $rawId = (string) ($raw['vk_comment_id'] ?? '');
            if ($rawId === '' || ! ctype_digit($rawId)) {
                throw new \InvalidArgumentException(
                    "invalid vk_comment_id: {$rawId}",
                );
            }
            $vkCommentId = (int) $rawId;
        }

        $url = trim((string) ($raw['url'] ?? ''));
        if ($url === '') {
            $url = $post->url.'?reply='.$vkCommentId;
        }

        $parentVkId = $this->nullableInt(
            $raw['parent_comment_id'] ?? $raw['parent_vk_comment_id'] ?? null
        );

        $attributes = [
            'parent_vk_comment_id' => $parentVkId,
            'text' => (string) ($raw['text'] ?? ''),
            'author_id' => $this->nullableInt($raw['author_id'] ?? null),
            'url' => $url,
            'posted_at' => $this->resolvePostedAt(
                $raw['posted_at'] ?? null,
                $raw['posted_at_raw'] ?? null,
            ),
        ];

        $existing = VkComment::query()
            ->where('post_id', $post->id)
            ->where('vk_comment_id', $vkCommentId)
            ->first();

        if ($existing) {
            $existing->fill($attributes)->save();

            return false;
        }

        VkComment::query()->create([
            'post_id' => $post->id,
            'vk_comment_id' => $vkCommentId,
            'parent_id' => null,
            'thread_root_id' => null,
            'depth' => 0,
            ...$attributes,
        ]);

        return true;
    }

    private function resolvePostedAt(mixed $iso, mixed $raw): Carbon
    {
        if (is_string($iso) && $iso !== '') {
            try {
                return Carbon::parse($iso);
            } catch (Throwable) {
                // fall through
            }
        }

        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (Throwable) {
                // fall through
            }
        }

        return now();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
