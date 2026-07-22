<?php

namespace App\Services\Vk;

use App\Models\Keyword;
use App\Models\Lead;
use App\Models\VkComment;
use App\Models\VkPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Match keywords against VK posts/comments and upsert Leads.
 *
 * Rules:
 * - keyword.type: post | comment | both
 * - match: case-insensitive substring after normalization (ё→е)
 * - score v1: +10 per matched keyword (one lead per keyword hit)
 * - dedupe via dedupe_key (unique)
 */
class LeadMatcher
{
    public const SCORE_PER_HIT = 10;

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function matchPost(VkPost $post): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $text = (string) ($post->text ?? '');

        if (trim($text) === '') {
            return $stats;
        }

        $keywords = $this->keywordsFor('post');

        foreach ($keywords as $keyword) {
            if (! $this->matches($text, $keyword->word)) {
                $stats['skipped']++;

                continue;
            }

            $created = $this->upsertLead(
                keyword: $keyword,
                sourceType: 'post',
                post: $post,
                comment: null,
                text: $text,
                url: (string) $post->url,
                groupId: (int) $post->group_id,
            );

            if ($created) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * Match a comment (including nested). Uses comment text only for match;
     * parent context is available later via Lead→comment relations.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function matchComment(VkComment $comment): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $text = (string) ($comment->text ?? '');

        if (trim($text) === '') {
            return $stats;
        }

        $comment->loadMissing('post');
        $post = $comment->post;

        if ($post === null) {
            return $stats;
        }

        $keywords = $this->keywordsFor('comment');

        foreach ($keywords as $keyword) {
            if (! $this->matches($text, $keyword->word)) {
                $stats['skipped']++;

                continue;
            }

            $url = (string) ($comment->url ?: $post->url);
            $created = $this->upsertLead(
                keyword: $keyword,
                sourceType: 'comment',
                post: $post,
                comment: $comment,
                text: $text,
                url: $url,
                groupId: (int) $post->group_id,
            );

            if ($created) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * Match a batch of posts (and optionally their comments already in DB).
     *
     * @param  iterable<VkPost>  $posts
     * @return array{created: int, updated: int, posts_checked: int, comments_checked: int}
     */
    public function matchPosts(iterable $posts, bool $withComments = true): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'posts_checked' => 0,
            'comments_checked' => 0,
        ];

        foreach ($posts as $post) {
            $stats['posts_checked']++;
            $r = $this->matchPost($post);
            $stats['created'] += $r['created'];
            $stats['updated'] += $r['updated'];

            if (! $withComments) {
                continue;
            }

            $comments = $post->relationLoaded('comments')
                ? $post->comments
                : $post->comments()->get();

            foreach ($comments as $comment) {
                $stats['comments_checked']++;
                $cr = $this->matchComment($comment);
                $stats['created'] += $cr['created'];
                $stats['updated'] += $cr['updated'];
            }
        }

        Log::info('vk.leads.matched', $stats);

        return $stats;
    }

    public function matches(string $haystack, string $needle): bool
    {
        $h = $this->normalize($haystack);
        $n = $this->normalize($needle);

        if ($n === '' || $h === '') {
            return false;
        }

        return mb_strpos($h, $n) !== false;
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        // collapse whitespace
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public function dedupeKey(string $sourceType, int $keywordId, int $postId, ?int $commentId): string
    {
        if ($sourceType === 'comment' && $commentId !== null) {
            return "c:{$commentId}:k:{$keywordId}";
        }

        return "p:{$postId}:k:{$keywordId}";
    }

    /**
     * @return Collection<int, Keyword>
     */
    private function keywordsFor(string $source): Collection
    {
        return Keyword::query()
            ->where(function ($q) use ($source) {
                $q->where('type', 'both')->orWhere('type', $source);
            })
            ->orderBy('id')
            ->get();
    }

    private function upsertLead(
        Keyword $keyword,
        string $sourceType,
        VkPost $post,
        ?VkComment $comment,
        string $text,
        string $url,
        int $groupId,
    ): bool {
        $commentId = $comment?->id;
        $key = $this->dedupeKey($sourceType, (int) $keyword->id, (int) $post->id, $commentId);

        $attributes = [
            'source_type' => $sourceType,
            'post_id' => $post->id,
            'comment_id' => $commentId,
            'group_id' => $groupId,
            'keyword_id' => $keyword->id,
            'text' => $text,
            'url' => $url,
            'score' => self::SCORE_PER_HIT,
            // Do not reset status on re-match if already processed
        ];

        $existing = Lead::query()->where('dedupe_key', $key)->first();

        if ($existing) {
            $existing->fill([
                'text' => $attributes['text'],
                'url' => $attributes['url'],
                'score' => $attributes['score'],
                // keep status, group_id, etc.
                'group_id' => $groupId,
            ])->save();

            return false;
        }

        Lead::query()->create([
            ...$attributes,
            'dedupe_key' => $key,
            'status' => 'new',
        ]);

        return true;
    }
}
