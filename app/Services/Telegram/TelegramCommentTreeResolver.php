<?php

namespace App\Services\Telegram;

use App\Models\TelegramComment;
use App\Models\TelegramPost;

class TelegramCommentTreeResolver
{
    public function resolveForPost(TelegramPost $post): void
    {
        $all = TelegramComment::query()->where('post_id', $post->id)->get();
        $by = [];
        foreach ($all as $c) {
            $by[$c->telegram_message_id] = $c;
        }foreach ($all as $c) {
            $p = $c->parent_telegram_message_id;
            $parent = $p ? $by[$p] ?? null : null;
            if (! $parent) {
                $c->update(['parent_id' => null, 'thread_root_id' => $c->id, 'depth' => 0]);

                continue;
            }$root = $parent->thread_root_id ?: $parent->id;
            $c->update(['parent_id' => $parent->id, 'thread_root_id' => $root, 'depth' => min(255, $parent->depth + 1)]);
        }
    }
}
