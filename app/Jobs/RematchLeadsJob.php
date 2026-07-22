<?php

namespace App\Jobs;

use App\Models\VkPost;
use App\Services\Vk\LeadMatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rematch all stored posts/comments against current keywords.
 * Unique so rapid keyword edits collapse into one job.
 */
class RematchLeadsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 60;

    public function __construct()
    {
        $this->onQueue('vk.scan');
    }

    public function uniqueId(): string
    {
        return 'rematch-leads';
    }

    public function handle(LeadMatcher $matcher): void
    {
        $posts = VkPost::query()->with('comments')->orderBy('id')->get();
        $stats = $matcher->matchPosts($posts, withComments: true);

        Log::info('vk.leads.rematch_job', $stats);
    }
}
