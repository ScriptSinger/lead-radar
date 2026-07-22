<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Keyword;

use App\Models\Keyword;
use App\MoonShine\Resources\Lead\LeadResource;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Keyword>
 */
class KeywordResource extends ModelResource
{
    protected string $model = Keyword::class;

    protected string $title = 'Keywords';

    protected string $column = 'word';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Word', 'word')->sortable(),
            Select::make('Type', 'type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
                'both' => 'Both',
            ])->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Word', 'word')->required(),
            Select::make('Type', 'type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
                'both' => 'Both',
            ])->default('both'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Word', 'word'),
            Select::make('Type', 'type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
                'both' => 'Both',
            ]),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }
}
