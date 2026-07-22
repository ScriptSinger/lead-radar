<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Lead;

use App\Models\Lead;
use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * @extends ModelResource<Lead>
 */
class LeadResource extends ModelResource
{
    protected string $model = Lead::class;

    protected string $title = 'Leads';

    protected string $column = 'id';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Select::make('Source type', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ])->sortable(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class),
            BelongsTo::make('Keyword', 'keyword', resource: KeywordResource::class),
            Text::make('Text', 'text'),
            Url::make('Url', 'url'),
            Number::make('Score', 'score')->sortable(),
            Select::make('Status', 'status')->options([
                'new' => 'New',
                'processed' => 'Processed',
                'ignored' => 'Ignored',
            ])->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Select::make('Source type', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ])->required(),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class)->required(),
            BelongsTo::make('Comment', 'comment', resource: VkCommentResource::class)->nullable(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class)->required(),
            BelongsTo::make('Keyword', 'keyword', resource: KeywordResource::class)->required(),
            Textarea::make('Text', 'text')->required(),
            Url::make('Url', 'url')->required(),
            Number::make('Score', 'score')->default(0),
            Select::make('Status', 'status')->options([
                'new' => 'New',
                'processed' => 'Processed',
                'ignored' => 'Ignored',
            ])->default('new'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Select::make('Source type', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ]),
            BelongsTo::make('Post', 'post', resource: VkPostResource::class),
            BelongsTo::make('Comment', 'comment', resource: VkCommentResource::class),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class),
            BelongsTo::make('Keyword', 'keyword', resource: KeywordResource::class),
            Textarea::make('Text', 'text'),
            Url::make('Url', 'url'),
            Number::make('Score', 'score'),
            Select::make('Status', 'status')->options([
                'new' => 'New',
                'processed' => 'Processed',
                'ignored' => 'Ignored',
            ]),
        ];
    }
}
