<?php

namespace App\Services\Vk;

use App\Models\VkComment;
use App\Models\VkPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resolve local adjacency-list links after flat comment upsert.
 *
 * Maps parent_vk_comment_id → parent_id / thread_root_id / depth.
 */
class CommentTreeResolver
{
    /**
     * Resolve tree for all comments of a post.
     *
     * @return array{resolved: int, roots: int, nested: int}
     */
    public function resolveForPost(VkPost $post): array
    {
        /** @var Collection<int, VkComment> $comments */
        $comments = VkComment::query()
            ->where('post_id', $post->id)
            ->get();

        if ($comments->isEmpty()) {
            return ['resolved' => 0, 'roots' => 0, 'nested' => 0];
        }

        /** @var array<int, VkComment> $byVkId */
        $byVkId = [];
        foreach ($comments as $comment) {
            $byVkId[(int) $comment->vk_comment_id] = $comment;
        }

        $roots = 0;
        $nested = 0;

        foreach ($comments as $comment) {
            $parentVk = $comment->parent_vk_comment_id;

            if ($parentVk === null || ! isset($byVkId[(int) $parentVk])) {
                // Root: parent missing or not in DB yet
                $comment->forceFill([
                    'parent_id' => null,
                    'thread_root_id' => $comment->id,
                    'depth' => 0,
                ])->save();
                $roots++;

                continue;
            }

            $parent = $byVkId[(int) $parentVk];
            $threadRoot = $this->findRoot($parent, $byVkId);
            $depth = $this->depthFromParent($parent, $byVkId);

            $comment->forceFill([
                'parent_id' => $parent->id,
                'thread_root_id' => $threadRoot->id,
                'depth' => $depth,
            ])->save();
            $nested++;
        }

        // Second pass: ensure thread_root_id of roots points to self
        // (already set). Replies that pointed at unresolved parents as roots
        // were handled when parent missing → treated as root.

        Log::debug('vk.comment_tree.resolved', [
            'post_id' => $post->id,
            'resolved' => $comments->count(),
            'roots' => $roots,
            'nested' => $nested,
        ]);

        return [
            'resolved' => $comments->count(),
            'roots' => $roots,
            'nested' => $nested,
        ];
    }

    /**
     * @param  array<int, VkComment>  $byVkId
     */
    private function findRoot(VkComment $comment, array $byVkId): VkComment
    {
        $cursor = $comment;
        $guard = 0;

        while ($cursor->parent_vk_comment_id !== null && $guard < 50) {
            $parentVk = (int) $cursor->parent_vk_comment_id;
            if (! isset($byVkId[$parentVk])) {
                break;
            }
            $cursor = $byVkId[$parentVk];
            $guard++;
        }

        return $cursor;
    }

    /**
     * @param  array<int, VkComment>  $byVkId
     */
    private function depthFromParent(VkComment $parent, array $byVkId): int
    {
        $depth = 1;
        $cursor = $parent;
        $guard = 0;

        while ($cursor->parent_vk_comment_id !== null && $guard < 50) {
            $parentVk = (int) $cursor->parent_vk_comment_id;
            if (! isset($byVkId[$parentVk])) {
                break;
            }
            $cursor = $byVkId[$parentVk];
            $depth++;
            $guard++;
        }

        return min($depth, 255);
    }
}
