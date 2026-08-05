<?php

namespace App\Services\Vk;

use App\Models\Keyword;
use App\Models\Lead;
use App\Models\TelegramPost;
use App\Models\VkComment;
use App\Models\VkPost;
use Illuminate\Database\QueryException;
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
            if (! $this->matchesKeyword($text, $keyword)) {
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
            if (! $this->matchesKeyword($text, $keyword)) {
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

    /** Match a Telegram channel post using the same keyword rules as VK. */
    public function matchTelegramPost(TelegramPost $post): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $text = (string) ($post->text ?? '');
        if (trim($text) === '') return $stats;
        foreach ($this->keywordsFor('post') as $keyword) {
            if (! $this->matchesKeyword($text, $keyword)) { $stats['skipped']++; continue; }
            $created = $this->upsertTelegramLead($keyword, $post, $text);
            $created ? $stats['created']++ : $stats['updated']++;
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

    /**
     * `whole_word` avoids matching inside another Cyrillic/Latin word. The
     * default `substring` preserves existing stem keyword behavior.
     */
    public function matches(string $haystack, string $needle, string $mode = 'substring'): bool
    {
        $h = $this->normalize($haystack);
        $n = $this->normalize($needle);

        if ($n === '' || $h === '') {
            return false;
        }

        if ($mode !== 'whole_word') {
            return mb_strpos($h, $n) !== false;
        }

        return preg_match(
            '/(?<![\p{L}\p{N}_])'.preg_quote($n, '/').'(?![\p{L}\p{N}_])/u',
            $h,
        ) === 1;
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        // collapse whitespace
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public function dedupeKey(string $sourceType, int $keywordId, int $postId, ?int $commentId, string $platform = 'vk'): string
    {
        if ($platform === 'telegram') {
            return "telegram:p:{$postId}:k:{$keywordId}";
        }
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
            'platform' => 'vk',
            'source_entity_type' => $sourceType,
            'source_entity_id' => $commentId ?? $post->id,
            'channel_or_group_id' => $groupId,
            'source_type' => $sourceType,
            'post_id' => $post->id,
            'comment_id' => $commentId,
            'group_id' => $groupId,
            'keyword_id' => $keyword->id,
            'text' => $text,
            'url' => $url,
            'score' => $this->scoreFor($keyword),
            // Do not reset status on re-match if already processed
        ];

        try {
            // Let the database's unique index arbitrate concurrent scans. A
            // read-then-insert here races when an admin/manual scan overlaps
            // the scheduled wave.
            Lead::query()->create([
                ...$attributes,
                'dedupe_key' => $key,
                'status' => 'new',
            ]);

            return true;
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }
        }

        // The conflicting row is committed before the duplicate-key error is
        // returned, so this lookup is safe. Keep the operator's status intact.
        $existing = Lead::query()->where('dedupe_key', $key)->firstOrFail();
        $existing->fill([
            'text' => $attributes['text'],
            'url' => $attributes['url'],
            'score' => $attributes['score'],
            'group_id' => $groupId,
        ])->save();

        return false;
    }

    private function upsertTelegramLead(Keyword $keyword, TelegramPost $post, string $text): bool
    {
        $key = $this->dedupeKey('post', (int) $keyword->id, (int) $post->id, null, 'telegram');
        $attributes = [
            'platform' => 'telegram',
            'source_entity_type' => 'post',
            'source_entity_id' => $post->id,
            'channel_or_group_id' => $post->channel_id,
            'source_type' => 'post',
            'post_id' => null, 'comment_id' => null, 'group_id' => null,
            'keyword_id' => $keyword->id, 'text' => $text, 'url' => (string) $post->url,
            'score' => $this->scoreFor($keyword),
        ];
        try {
            Lead::query()->create([...$attributes, 'dedupe_key' => $key, 'status' => 'new']);
            return true;
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) throw $e;
        }
        Lead::query()->where('dedupe_key', $key)->firstOrFail()->fill($attributes)->save();
        return false;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            || str_contains(mb_strtolower($e->getMessage()), 'unique constraint');
    }

    private function matchesKeyword(string $text, Keyword $keyword): bool
    {
        if (! $this->matches($text, $keyword->word, $keyword->match_mode ?? 'substring')) {
            return false;
        }

        foreach ($this->negativeWords($keyword->negative_words) as $negative) {
            if ($this->matches($text, $negative, 'substring')) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function negativeWords(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[,\r\n]+/u', $value) ?: [],
        )));
    }

    private function scoreFor(Keyword $keyword): int
    {
        return max(1, min(1000, (int) ($keyword->score ?: self::SCORE_PER_HIT)));
    }
}
