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

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class)
                ->sortable(),
            Number::make('Depth', 'depth')->sortable(),
            Number::make('VK id', 'vk_comment_id')->sortable(),
            Number::make('Parent VK', 'parent_vk_comment_id'),
            BelongsTo::make('Parent', 'parent', resource: self::class),
            BelongsTo::make('Thread root', 'threadRoot', resource: self::class),
            Text::make('Text', 'text'),
            Number::make('Author', 'author_id'),
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
            Number::make('Parent VK id', 'parent_vk_comment_id'),
            BelongsTo::make('Parent', 'parent', resource: self::class)->nullable(),
            BelongsTo::make('Thread root', 'threadRoot', resource: self::class)->nullable(),
            Number::make('Depth', 'depth')->default(0),
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
            Number::make('Parent VK id', 'parent_vk_comment_id'),
            BelongsTo::make('Parent', 'parent', resource: self::class),
            BelongsTo::make('Thread root', 'threadRoot', resource: self::class),
            Number::make('Depth', 'depth'),
            Textarea::make('Text', 'text'),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i:s'),
            HasMany::make('Replies', 'children', resource: self::class),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }
}
