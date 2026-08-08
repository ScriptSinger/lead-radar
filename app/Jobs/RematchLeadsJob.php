<?php

namespace App\Jobs;

use App\Models\TelegramPost;
use App\Models\VkPost;
use App\Services\LeadMatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rematch stored VK and Telegram content against current keywords.
 * Unique so rapid keyword edits collapse into one job.
 */
class RematchLeadsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 60;

    public function __construct()
    {
        $this->onConnection('redis')->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'rematch-leads';
    }

    public function handle(LeadMatcher $matcher): void
    {
        $vkStats = $matcher->matchPosts(
            VkPost::query()->with('comments')->orderBy('id')->get(),
            withComments: true,
        );
        $telegramStats = ['created' => 0, 'updated' => 0, 'posts_checked' => 0, 'comments_checked' => 0];

        TelegramPost::query()->with('comments')->orderBy('id')->each(function (TelegramPost $post) use ($matcher, &$telegramStats): void {
            $telegramStats['posts_checked']++;
            $postStats = $matcher->matchTelegramPost($post);
            $telegramStats['created'] += $postStats['created'];
            $telegramStats['updated'] += $postStats['updated'];

            foreach ($post->comments as $comment) {
                $telegramStats['comments_checked']++;
                $commentStats = $matcher->matchTelegramComment($comment);
                $telegramStats['created'] += $commentStats['created'];
                $telegramStats['updated'] += $commentStats['updated'];
            }
        });

        Log::info('leads.rematch_job', [
            'vk' => $vkStats,
            'telegram' => $telegramStats,
        ]);
    }
}
