<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost;

use App\Models\VkPost;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\Pages\VkPostDetailPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostFormPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use MoonShine\Contracts\Core\PageContract;
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
 * @extends ModelResource<VkPost, VkPostIndexPage, VkPostFormPage, VkPostDetailPage>
 */
class VkPostResource extends ModelResource
{
    protected string $model = VkPost::class;

    protected string $title = 'VK Posts';

    /** Label in BelongsTo / filters (accessor on VkPost) */
    protected string $column = 'display_name';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            // relation method on model is group(), not VkGroup()
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class)
                ->sortable(),
            Text::make('VK post id', 'vk_post_id')->sortable(),
            Text::make('Text', 'text'),
            Url::make('Url', 'url'),
            Number::make('Author id', 'author_id'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class)
                ->required(),
            Text::make('VK post id', 'vk_post_id')->required(),
            Textarea::make('Text', 'text'),
            Url::make('Url', 'url')->required(),
            Number::make('Author id', 'author_id'),
            Date::make('Posted at', 'posted_at')->withTime(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class),
            Text::make('VK post id', 'vk_post_id'),
            Textarea::make('Text', 'text'),
            Url::make('Url', 'url'),
            Number::make('Author id', 'author_id'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i:s'),
            // relation method on model is comments(), not VkComments()
            HasMany::make('Comments', 'comments', resource: VkCommentResource::class),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            VkPostIndexPage::class,
            VkPostFormPage::class,
            VkPostDetailPage::class,
        ];
    }
}
