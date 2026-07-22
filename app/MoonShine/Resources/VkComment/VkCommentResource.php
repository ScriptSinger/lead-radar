<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkComment;

use App\Models\VkComment;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * @extends ModelResource<VkComment>
 */
class VkCommentResource extends ModelResource
{
    protected string $model = VkComment::class;

    protected string $title = 'VK Comments';

    protected string $column = 'vk_comment_id';

    /**
     * parent_comment_id stores the VK reply id (not local PK), so we show it
     * as a number instead of a broken BelongsTo parent relation.
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class)
                ->sortable(),
            Number::make('VK comment id', 'vk_comment_id')->sortable(),
            Number::make('Parent VK id', 'parent_comment_id'),
            Text::make('Text', 'text'),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class)
                ->required(),
            Number::make('VK comment id', 'vk_comment_id')->required(),
            Number::make('Parent VK id', 'parent_comment_id'),
            Textarea::make('Text', 'text')->required(),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->withTime(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class),
            Number::make('VK comment id', 'vk_comment_id'),
            Number::make('Parent VK id', 'parent_comment_id'),
            Textarea::make('Text', 'text'),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i:s'),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }
}
