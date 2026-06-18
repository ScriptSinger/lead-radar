<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost;

use App\Models\VkPost;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\PageContract;

use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

class VkPostResource extends ModelResource
{
    protected string $model = VkPost::class;

    protected string $title = 'VK Posts';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            // BelongsTo::make('Group', 'group', VkGroupResource::class),
            Text::make('Vk post id'),
            Text::make('Text'),
            Url::make('Url'),
            Text::make('Author id'),
            Date::make('Posted at'),
            // HasMany::make('Comments', 'comments'),
            HasMany::make('Leads', 'leads'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make()->sortable(),
            // BelongsTo::make('Group', 'group', 'name'),
            Text::make('Vk post id'),
            Text::make('Text'),
            Url::make('Url'),
            Text::make('Author id'),
            Date::make('Posted at'),
            // HasMany::make('Comments', 'comments'),
            HasMany::make('Leads', 'leads'),
        ];
    }
}
