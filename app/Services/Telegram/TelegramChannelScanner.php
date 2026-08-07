<?php

namespace App\Services\Telegram;

use App\Models\TelegramChannel;
use App\Models\TelegramComment;
use App\Models\TelegramPost;
use App\Models\TelegramScanRun;
use App\Modules\Telegram\TelegramContentSource;
use App\Services\LeadMatcher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Persists a channel's latest posts and creates keyword-matched leads. */
class TelegramChannelScanner
{
    public function __construct(
        private readonly TelegramContentSource $source,
        private readonly LeadMatcher $leadMatcher,
        private readonly TelegramCommentTreeResolver $treeResolver,
    ) {}

    /** @return array<string, mixed> */
    public function scan(TelegramChannel $channel, int $limit = 20, string $trigger = 'manual', bool $withComments = true, int $commentsLimit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $started = microtime(true);
        $run = TelegramScanRun::start($channel, $trigger, $limit);
        $cutoff = $channel->last_scan_at;
        $stats = [
            'channel_id' => $channel->id,
            'scan_run_id' => $run->id,
            'posts_fetched' => 0, 'posts_created' => 0, 'posts_updated' => 0,
            'posts_in_window' => 0, 'posts_outside_window' => 0, 'leads_created' => 0, 'leads_updated' => 0,
            'window_cutoff' => $cutoff?->toIso8601String(), 'errors' => [], 'duration_ms' => 0,
        ];

        try {
            $result = $this->source->fetchChannel($channel->url, $limit);
            $peer = $result['channel'];
            $channel->fill($peer)->save();
            $channel->refresh();

            $stats['posts_fetched'] = count($result['posts']);
            $latestMessageId = null;
            $windowPosts = [];
            foreach ($result['posts'] as $raw) {
                [$post, $created] = $this->upsertPost($channel, $raw);
                $created ? $stats['posts_created']++ : $stats['posts_updated']++;
                if ($this->inWindow($post->posted_at, $cutoff)) {
                    $stats['posts_in_window']++;
                    $windowPosts[] = $post;
                } else {
                    $stats['posts_outside_window']++;
                }
                $latestMessageId = max($latestMessageId ?? 0, (int) $post->telegram_message_id);
            }

            foreach ($windowPosts as $post) {
                $matched = $this->leadMatcher->matchTelegramPost($post);
                $stats['leads_created'] += $matched['created'];
                $stats['leads_updated'] += $matched['updated'];
                if (! $withComments) {
                    continue;
                }
                foreach ($this->source->fetchComments($post, $commentsLimit) as $rawComment) {
                    $comment = TelegramComment::query()->updateOrCreate(['post_id' => $post->id, 'telegram_message_id' => $rawComment['telegram_message_id']], $rawComment);
                    $matched = $this->leadMatcher->matchTelegramComment($comment);
                    $stats['leads_created'] += $matched['created'];
                    $stats['leads_updated'] += $matched['updated'];
                }
                $this->treeResolver->resolveForPost($post);
            }

            if ($stats['posts_fetched'] > 0) {
                $channel->forceFill([
                    'last_scan_at' => now(),
                    'last_message_id' => $latestMessageId,
                ])->save();
            }

            $stats['duration_ms'] = $this->elapsed($started);
            $run->markSuccess($stats, $stats['duration_ms']);
            Log::info('telegram.scan.finished', $stats);

            return $stats;
        } catch (Throwable $e) {
            $stats['duration_ms'] = $this->elapsed($started);
            $stats['errors'][] = $e->getMessage();
            $run->markFailed($e->getMessage(), $stats['duration_ms'], $stats);
            Log::error('telegram.scan.failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $raw @return array{TelegramPost, bool} */
    private function upsertPost(TelegramChannel $channel, array $raw): array
    {
        $id = (int) ($raw['telegram_message_id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('telegram_message_id is empty');
        }

        $post = TelegramPost::query()->where([
            'channel_id' => $channel->id,
            'telegram_message_id' => $id,
        ])->first();
        $attributes = [
            'text' => $raw['text'] ?? null,
            'url' => (string) ($raw['url'] ?? ''),
            'author_telegram_id' => $raw['author_telegram_id'] ?? null,
            'has_media' => (bool) ($raw['has_media'] ?? false),
            'posted_at' => $raw['posted_at'],
        ];

        if ($post) {
            $post->fill($attributes)->save();

            return [$post, false];
        }

        return [TelegramPost::query()->create(['channel_id' => $channel->id, 'telegram_message_id' => $id, ...$attributes]), true];
    }

    private function inWindow(?CarbonInterface $postedAt, ?CarbonInterface $cutoff): bool
    {
        return $cutoff === null || ($postedAt !== null && $postedAt->greaterThan($cutoff));
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
