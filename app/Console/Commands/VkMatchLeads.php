<?php

namespace App\Console\Commands;

use App\Models\VkPost;
use App\Services\Vk\LeadMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vk:match-leads {--group= : Only posts from this group id} {--post= : Single post id} {--with-comments=1 : Match comments too}')]
#[Description('Re-run keyword matching on stored VK posts/comments without scraping')]
class VkMatchLeads extends Command
{
    public function handle(LeadMatcher $matcher): int
    {
        $query = VkPost::query()->orderBy('id');

        if ($groupId = $this->option('group')) {
            $query->where('group_id', (int) $groupId);
        }

        if ($postId = $this->option('post')) {
            $query->whereKey((int) $postId);
        }

        $posts = $query->with('comments')->get();

        if ($posts->isEmpty()) {
            $this->warn('No posts to match.');

            return self::SUCCESS;
        }

        $withComments = filter_var($this->option('with-comments'), FILTER_VALIDATE_BOOL);

        $this->info(sprintf(
            'Matching %d post(s), comments=%s',
            $posts->count(),
            $withComments ? 'yes' : 'no',
        ));

        $stats = $matcher->matchPosts($posts, $withComments);

        $this->info(sprintf(
            'Done. leads created=%d updated=%d (posts=%d comments=%d checked)',
            $stats['created'],
            $stats['updated'],
            $stats['posts_checked'],
            $stats['comments_checked'],
        ));

        return self::SUCCESS;
    }
}
