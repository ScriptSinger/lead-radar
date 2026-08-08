<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram;

use App\Models\TelegramChannel;
use App\Models\TelegramPost;
use App\MoonShine\Resources\Telegram\Pages\TelegramPostDetailPage;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/** @extends ModelResource<TelegramPost> */
class TelegramPostResource extends ModelResource
{
    protected string $model = TelegramPost::class;

    protected string $title = 'Telegram Posts';

    protected string $column = 'display_name';

    protected array $with = ['channel'];

    protected string $sortColumn = 'posted_at';

    protected SortDirection $sortDirection = SortDirection::DESC;

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $workspaceId = WorkspaceContext::id();
        abort_if($workspaceId === null, 403, 'Select a workspace first.');

        return $builder
            ->whereHas('channel', static fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->withCount('comments');
    }

    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $this->modifyQueryBuilder($builder);
    }

    protected function pages(): array
    {
        return [IndexPage::class, FormPage::class, TelegramPostDetailPage::class];
    }

    protected function indexFields(): iterable
    {
        return [ID::make()->sortable(), BelongsTo::make('Channel', 'channel', formatted: static fn (TelegramChannel $channel): string => $channel->name, resource: TelegramChannelResource::class)->sortable(), Number::make('Message ID', 'telegram_message_id')->sortable(), Text::make('Text', 'text'), Number::make('Comments', 'comments_count')->sortable(), Switcher::make('Media', 'has_media')->sortable(), Url::make('URL', 'url')->blank(), Date::make('Posted', 'posted_at')->format('Y-m-d H:i')->sortable()];
    }

    protected function formFields(): iterable
    {
        return [Textarea::make('Text', 'text'), Url::make('URL', 'url')->required(), Date::make('Posted', 'posted_at')->withTime()];
    }

    protected function detailFields(): iterable
    {
        return [ID::make(), BelongsTo::make('Channel', 'channel', resource: TelegramChannelResource::class), Number::make('Message ID', 'telegram_message_id'), Textarea::make('Text', 'text'), Url::make('URL', 'url')->blank(), Date::make('Posted', 'posted_at')->format('Y-m-d H:i:s')];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Channel', 'channel', formatted: static fn (TelegramChannel $channel): string => $channel->name, resource: TelegramChannelResource::class)->nullable(),
            Number::make('Message ID', 'telegram_message_id')->nullable(),
            Text::make('Text', 'text')->placeholder('Search post text'),
            Switcher::make('Media', 'has_media')->nullable(),
            Date::make('Posted on', 'posted_at')->nullable(),
        ];
    }
}
