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
 *
 * When parent_vk is set but the parent row is not in DB yet (incomplete scrape),
 * the comment stays at top of the tree (parent_id null, depth 0) so it remains
 * visible — but it is counted as an orphan, not a true root.
 */
class CommentTreeResolver
{
    /**
     * Resolve tree for all comments of a post.
     *
     * @return array{resolved: int, roots: int, nested: int, orphans: int}
     */
    public function resolveForPost(VkPost $post): array
    {
        /** @var Collection<int, VkComment> $comments */
        $comments = VkComment::query()
            ->where('post_id', $post->id)
            ->get();

        if ($comments->isEmpty()) {
            return ['resolved' => 0, 'roots' => 0, 'nested' => 0, 'orphans' => 0];
        }

        /** @var array<int, VkComment> $byVkId */
        $byVkId = [];
        foreach ($comments as $comment) {
            $byVkId[(int) $comment->vk_comment_id] = $comment;
        }

        $roots = 0;
        $nested = 0;
        $orphans = 0;
        /** @var list<int> $orphanParentVks */
        $orphanParentVks = [];

        foreach ($comments as $comment) {
            $parentVk = $comment->parent_vk_comment_id;

            // True root: VK API did not report a parent
            if ($parentVk === null) {
                $comment->forceFill([
                    'parent_id' => null,
                    'thread_root_id' => $comment->id,
                    'depth' => 0,
                ])->save();
                $roots++;

                continue;
            }

            $parentVkInt = (int) $parentVk;

            // Self-parent → treat as true root (corrupt payload)
            if ($parentVkInt === (int) $comment->vk_comment_id) {
                $comment->forceFill([
                    'parent_vk_comment_id' => null,
                    'parent_id' => null,
                    'thread_root_id' => $comment->id,
                    'depth' => 0,
                ])->save();
                $roots++;

                continue;
            }

            // Orphan: parent claimed but not in this post's comments
            if (! isset($byVkId[$parentVkInt])) {
                $comment->forceFill([
                    'parent_id' => null,
                    'thread_root_id' => $comment->id,
                    'depth' => 0,
                ])->save();
                $orphans++;
                $orphanParentVks[] = $parentVkInt;

                continue;
            }

            $parent = $byVkId[$parentVkInt];
            $threadRoot = $this->findRoot($parent, $byVkId);
            $depth = $this->depthFromParent($parent, $byVkId);

            $comment->forceFill([
                'parent_id' => $parent->id,
                'thread_root_id' => $threadRoot->id,
                'depth' => $depth,
            ])->save();
            $nested++;
        }

        Log::debug('vk.comment_tree.resolved', [
            'post_id' => $post->id,
            'resolved' => $comments->count(),
            'roots' => $roots,
            'nested' => $nested,
            'orphans' => $orphans,
            'orphan_missing_parent_vks' => array_values(array_unique($orphanParentVks)),
        ]);

        return [
            'resolved' => $comments->count(),
            'roots' => $roots,
            'nested' => $nested,
            'orphans' => $orphans,
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
