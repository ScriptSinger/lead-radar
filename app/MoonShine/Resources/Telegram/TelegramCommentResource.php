<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram;

use App\Models\TelegramComment;
use App\Models\TelegramPost;
use App\MoonShine\Resources\Telegram\Pages\TelegramCommentIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Leeto\MoonShineTree\Resources\TreeResource;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/** Nested Telegram discussion replies, displayed under their source post. */
class TelegramCommentResource extends TreeResource
{
    protected string $model = TelegramComment::class;

    protected string $title = 'Telegram Comments';

    protected string $column = 'text';

    protected string $sortColumn = 'posted_at';

    protected SortDirection $sortDirection = SortDirection::ASC;

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

    public function wrappable(): bool
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

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Number::make('Telegram ID', 'telegram_message_id')->sortable(),
            Number::make('Depth', 'depth'),
            Textarea::make('Text', 'text'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            TelegramCommentIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Post', 'post', formatted: static fn (TelegramPost $post): string => $post->display_name, resource: TelegramPostResource::class)->nullable(),
            Number::make('Author ID', 'author_telegram_id')->nullable(),
            Text::make('Text', 'text')->placeholder('Search comment text'),
            Date::make('Posted on', 'posted_at')->nullable(),
        ];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder
            ->orderByRaw('COALESCE(thread_root_id, id) ASC')
            ->orderBy('depth')
            ->orderBy('posted_at')
            ->orderBy('id');
    }
}
