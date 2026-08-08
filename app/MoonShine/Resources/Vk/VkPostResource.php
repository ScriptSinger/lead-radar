<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Vk;

use App\Models\VkGroup;
use App\Models\VkPost;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\Vk\Pages\VkPostDetailPage;
use App\MoonShine\Resources\Vk\Pages\VkPostFormPage;
use App\MoonShine\Resources\Vk\Pages\VkPostIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
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

    /** Avoid N+1 on index/detail BelongsTo */
    protected array $with = ['group'];

    protected string $sortColumn = 'posted_at';

    protected SortDirection $sortDirection = SortDirection::DESC;

    /**
     * Aggregate for index column `comments_count` (see indexFields).
     */
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('comments');
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            // relation method on model is group(), not VkGroup()
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class)
                ->sortable(),
            Text::make('VK post id', 'vk_post_id')->sortable(),
            Text::make('Text', 'text'),
            Number::make('Comments', 'comments_count')->sortable(),
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
            // Comments: nested tree on VkPostDetailPage (PostCommentsTree), not flat HasMany
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Group', 'group', formatted: static fn (VkGroup $group): string => $group->name, resource: VkGroupResource::class)->nullable(),
            Number::make('VK post ID', 'vk_post_id')->nullable(),
            Text::make('Text', 'text')->placeholder('Search post text'),
            Number::make('Author ID', 'author_id')->nullable(),
            Date::make('Posted on', 'posted_at')->nullable(),
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
