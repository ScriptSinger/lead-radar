<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\TelegramComment;
use Illuminate\Database\Eloquent\Model;
use Leeto\MoonShineTree\Resources\TreeResource;
use MoonShine\Support\Enums\Color;

/** Nested Telegram discussion replies, displayed under their source post. */
class TelegramCommentResource extends TreeResource
{
    protected string $model = TelegramComment::class;

    protected string $title = 'Telegram Comments';

    protected string $column = 'text';

    protected string $sortColumn = 'posted_at';

    protected array $with = ['post.channel', 'parent', 'children'];

    public function treeKey(): ?string
    {
        return 'parent_id';
    }

    public function sortKey(): string
    {
        return 'posted_at';
    }

    public function sortable(): bool
    {
        return false;
    }

    public function compactTree(): bool
    {
        return true;
    }

    public function treeItemTitle(Model $item): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $item->text) ?? '');

        return $text !== '' ? mb_strimwidth($text, 0, 120, '…') : '#'.$item->telegram_message_id;
    }

    public function treeItemBadgeText(Model $item): string
    {
        return $item->parent_id ? 'reply · '.$item->telegram_message_id : 'root · '.$item->telegram_message_id;
    }

    public function treeItemBadgeColor(Model $item): Color
    {
        return $item->parent_id ? Color::BLUE : Color::GREEN;
    }

    public function treeItemDescription(Model $item): string
    {
        $parts = array_filter([
            $item->post?->channel?->name,
            $item->posted_at?->format('Y-m-d H:i'),
            $item->author_telegram_id ? 'author: '.$item->author_telegram_id : null,
        ]);

        return implode(' · ', $parts);
    }
}
