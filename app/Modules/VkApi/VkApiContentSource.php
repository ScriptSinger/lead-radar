<?php

namespace App\Modules\VkApi;

use App\Contracts\VkContentSource;
use App\Models\VkPost;
use Carbon\Carbon;

/** Official VK API implementation of the scanner's normalized content source. */
class VkApiContentSource implements VkContentSource
{
    /** @var array<string, int> */
    private array $ownerIds = [];

    public function __construct(private readonly VkApiClient $client) {}

    public function health(): bool
    {
        return $this->client->health();
    }

    public function fetchGroup(string $url, int $limit): array
    {
        $ownerId = $this->ownerId($url);
        $wall = $this->client->call('wall.get', [
            'owner_id' => $ownerId,
            'count' => max(1, min(100, $limit)),
            'filter' => 'owner',
        ]);

        $items = $wall['items'] ?? [];
        if (! is_array($items)) {
            throw new VkApiException('VK API wall.get response is missing items.');
        }

        return array_values(array_map(
            fn (array $post): array => $this->normalizePost($post, $ownerId),
            array_filter($items, 'is_array'),
        ));
    }

    public function fetchComments(VkPost $post): array
    {
        [$ownerId, $postId] = $this->wallIds($post->vk_post_id);
        $comments = [];

        for ($offset = 0; ; $offset += 100) {
            $page = $this->client->call('wall.getComments', [
                'owner_id' => $ownerId,
                'post_id' => $postId,
                'count' => 100,
                'offset' => $offset,
                'sort' => 'asc',
                'thread_items_count' => 10,
            ]);
            $items = is_array($page['items'] ?? null) ? $page['items'] : [];

            foreach ($items as $comment) {
                if (! is_array($comment)) {
                    continue;
                }
                $comments[] = $this->normalizeComment($comment, $post, null);
                $thread = is_array($comment['thread'] ?? null) ? $comment['thread'] : [];
                $preview = is_array($thread['items'] ?? null) ? $thread['items'] : [];
                foreach ($preview as $reply) {
                    if (is_array($reply)) {
                        $comments[] = $this->normalizeComment(
                            $reply,
                            $post,
                            $this->parentCommentId($reply, (int) $comment['id']),
                        );
                    }
                }

                $threadCount = (int) ($thread['count'] ?? count($preview));
                if ($threadCount > count($preview)) {
                    $comments = [...$comments, ...$this->fetchThreadReplies(
                        $ownerId,
                        $postId,
                        $post,
                        (int) $comment['id'],
                        $threadCount,
                    )];
                }
            }

            if (count($items) < 100) {
                break;
            }
        }

        return $this->uniqueNonEmptyComments($comments);
    }

    /**
     * `thread.items` is only a preview. Load all remaining replies using the
     * documented thread anchor, so nested keyword matching matches the former
     * parser behaviour instead of silently stopping at the preview.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchThreadReplies(
        int $ownerId,
        int $postId,
        VkPost $post,
        int $rootCommentId,
        int $expectedCount,
    ): array {
        $replies = [];

        for ($offset = 0; $offset < $expectedCount; $offset += 100) {
            $page = $this->client->call('wall.getComments', [
                'owner_id' => $ownerId,
                'post_id' => $postId,
                'start_comment_id' => $rootCommentId,
                'count' => 100,
                'offset' => $offset,
                'sort' => 'asc',
            ]);
            $items = is_array($page['items'] ?? null) ? $page['items'] : [];

            foreach ($items as $reply) {
                if (! is_array($reply) || (int) ($reply['id'] ?? 0) === $rootCommentId) {
                    continue;
                }
                $replies[] = $this->normalizeComment(
                    $reply,
                    $post,
                    $this->parentCommentId($reply, $rootCommentId),
                );
            }

            if (count($items) < 100) {
                break;
            }
        }

        return $replies;
    }

    private function ownerId(string $url): int
    {
        if (isset($this->ownerIds[$url])) {
            return $this->ownerIds[$url];
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $screenName = explode('/', $path)[0] ?? '';
        if ($screenName === '') {
            throw new VkApiException("Cannot resolve VK screen name from {$url}.");
        }

        $resolved = $this->client->call('utils.resolveScreenName', ['screen_name' => $screenName]);
        $objectId = isset($resolved['object_id']) ? (int) $resolved['object_id'] : 0;
        $type = (string) ($resolved['type'] ?? '');
        if ($objectId <= 0 || ! in_array($type, ['group', 'page', 'event'], true)) {
            throw new VkApiException("VK community was not found for {$url}.");
        }

        return $this->ownerIds[$url] = -$objectId;
    }

    /** @param array<string, mixed> $post */
    private function normalizePost(array $post, int $ownerId): array
    {
        $postId = (int) ($post['id'] ?? 0);
        $postOwnerId = (int) ($post['owner_id'] ?? $ownerId);
        if ($postId <= 0) {
            throw new VkApiException('VK API returned a wall post without id.');
        }

        return [
            'vk_post_id' => "{$postOwnerId}_{$postId}",
            'text' => (string) ($post['text'] ?? ''),
            'url' => "https://vk.com/wall{$postOwnerId}_{$postId}",
            'posted_at' => $this->isoDate($post['date'] ?? null),
            'author_id' => $post['from_id'] ?? $postOwnerId,
            'author_type' => ((int) ($post['from_id'] ?? $postOwnerId)) < 0 ? 'group' : 'user',
        ];
    }

    /** @param array<string, mixed> $comment */
    private function normalizeComment(array $comment, VkPost $post, ?int $parentId): array
    {
        $id = (int) ($comment['id'] ?? 0);
        if ($id <= 0) {
            throw new VkApiException('VK API returned a comment without id.');
        }
        $fromId = isset($comment['from_id']) ? (int) $comment['from_id'] : null;

        return [
            'vk_comment_id' => $id,
            'parent_comment_id' => $parentId,
            'text' => (string) ($comment['text'] ?? ''),
            'url' => $post->url.'?reply='.$id,
            'posted_at' => $this->isoDate($comment['date'] ?? null),
            'author_id' => $fromId,
            'author_type' => $fromId !== null && $fromId < 0 ? 'group' : 'user',
        ];
    }

    /** @param array<string, mixed> $comment */
    private function parentCommentId(array $comment, int $fallback): int
    {
        $parents = $comment['parents_stack'] ?? null;
        if (is_array($parents) && $parents !== []) {
            $parent = end($parents);
            if (is_numeric($parent) && (int) $parent > 0) {
                return (int) $parent;
            }
        }

        return $fallback;
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     * @return list<array<string, mixed>>
     */
    private function uniqueNonEmptyComments(array $comments): array
    {
        $unique = [];
        foreach ($comments as $comment) {
            $id = (int) ($comment['vk_comment_id'] ?? 0);
            if ($id <= 0 || trim((string) ($comment['text'] ?? '')) === '') {
                continue;
            }

            // A full-thread request may repeat an item from thread.items.
            // Keep the version with a known parent relationship.
            if (! isset($unique[$id]) || ($unique[$id]['parent_comment_id'] === null && $comment['parent_comment_id'] !== null)) {
                $unique[$id] = $comment;
            }
        }

        return array_values($unique);
    }

    /** @return array{0: int, 1: int} */
    private function wallIds(string $vkPostId): array
    {
        if (preg_match('/^(-?\d+)_(\d+)$/', $vkPostId, $matches) !== 1) {
            throw new VkApiException("Invalid VK wall post id: {$vkPostId}");
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function isoDate(mixed $timestamp): ?string
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp)->toIso8601String() : null;
    }
}
