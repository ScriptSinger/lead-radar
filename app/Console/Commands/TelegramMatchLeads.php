<?php

namespace App\Console\Commands;

use App\Models\TelegramPost;
use App\Services\LeadMatcher;
use Illuminate\Console\Command;

class TelegramMatchLeads extends Command
{
    protected $signature = 'telegram:match-leads {--channel=}';

    protected $description = 'Re-match saved Telegram posts and comments against current keywords';

    public function handle(LeadMatcher $matcher): int
    {
        $created = $updated = $commentsChecked = 0;
        TelegramPost::query()->with('comments')->when($this->option('channel'), fn ($q, $id) => $q->where('channel_id', (int) $id))->orderBy('id')->each(function (TelegramPost $post) use ($matcher, &$created, &$updated, &$commentsChecked) {
            $r = $matcher->matchTelegramPost($post);
            $created += $r['created'];
            $updated += $r['updated'];

            foreach ($post->comments as $comment) {
                $commentsChecked++;
                $r = $matcher->matchTelegramComment($comment);
                $created += $r['created'];
                $updated += $r['updated'];
            }
        });
        $this->info("Telegram leads: created={$created}, updated={$updated}, comments checked={$commentsChecked}");

        return self::SUCCESS;
    }
}
