<?php

namespace App\Console\Commands;

use App\Models\VkPost;
use App\Services\Vk\CommentTreeResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vk:resolve-comment-trees {--post= : Resolve a single post by id}')]
#[Description('Recompute parent_id / thread_root_id / depth for VK comments')]
class VkResolveCommentTrees extends Command
{
    public function handle(CommentTreeResolver $resolver): int
    {
        $postId = $this->option('post');

        $query = VkPost::query()->whereHas('comments')->orderBy('id');

        if ($postId !== null && $postId !== '') {
            $query->whereKey((int) $postId);
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->warn('No posts with comments found.');

            return self::SUCCESS;
        }

        $totalRoots = 0;
        $totalNested = 0;

        foreach ($posts as $post) {
            $stats = $resolver->resolveForPost($post);
            $totalRoots += $stats['roots'];
            $totalNested += $stats['nested'];

            $this->line(sprintf(
                'post #%d (%s): resolved=%d roots=%d nested=%d',
                $post->id,
                $post->vk_post_id,
                $stats['resolved'],
                $stats['roots'],
                $stats['nested'],
            ));
        }

        $this->info(sprintf(
            'Done. posts=%d roots=%d nested=%d',
            $posts->count(),
            $totalRoots,
            $totalNested,
        ));

        return self::SUCCESS;
    }
}
