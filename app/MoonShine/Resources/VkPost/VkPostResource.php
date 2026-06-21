<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost;

use Illuminate\Database\Eloquent\Model;
use App\Models\VkPost;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostFormPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;

use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

/**
 * @extends ModelResource<VkPost, VkPostIndexPage, VkPostFormPage, VkPostDetailPage>
 */
class VkPostResource extends ModelResource
{
    protected string $model = VkPost::class;

    protected string $title = 'VkPosts';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('VkGroup', 'VkGroup', VkGroupResource::class),
            Text::make('Vk post id'),
            Text::make('Text'),
            Url::make('Url'),
            Text::make('Author id'),
            Date::make('Posted at'),
            HasMany::make('VkComments', 'VkComments', VkCommentResource::class),
            HasMany::make('Leads', 'leads'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('VkGroup', 'VkGroup', VkGroupResource::class),
            Text::make('Vk post id'),
            Text::make('Text'),
            Url::make('Url'),
            Text::make('Author id'),
            Date::make('Posted at'),
            HasMany::make('VkComments', 'VkComments', VkCommentResource::class),
            HasMany::make('Leads', 'leads'),
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
