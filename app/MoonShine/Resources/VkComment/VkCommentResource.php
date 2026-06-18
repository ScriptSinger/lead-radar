<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkComment;

use App\Models\VkComment;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\PageContract;


use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

class VkCommentResource extends ModelResource
{
    protected string $model = VkComment::class;

    protected string $title = 'VK Comments';

    public function fields(): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Post', 'post', 'id'),
            Text::make('Vk comment id'),
            BelongsTo::make('Parent', 'parent', 'id'),
            Text::make('Text'),
            Text::make('Author id'),
            Url::make('Url'),
            Date::make('Posted at'),
            HasMany::make('Children', 'children'),
            HasMany::make('Leads', 'leads'),
        ];
    }
}
