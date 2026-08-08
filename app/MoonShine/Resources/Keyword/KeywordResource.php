<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Keyword;

use App\Models\Keyword;
use App\MoonShine\Concerns\ScopesToActiveWorkspace;
use App\MoonShine\Resources\Lead\LeadResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Keyword>
 */
class KeywordResource extends ModelResource
{
    use ScopesToActiveWorkspace;

    protected string $model = Keyword::class;

    protected string $title = 'Keywords';

    protected string $column = 'word';

    protected string $sortColumn = 'word';

    protected SortDirection $sortDirection = SortDirection::ASC;

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
            Select::make('Match mode', 'match_mode')->options([
                'substring' => 'Substring (supports stems)',
                'whole_word' => 'Whole word / phrase',
            ])->sortable(),
            Text::make('Score', 'score')->sortable(),
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
            Select::make('Match mode', 'match_mode')->options([
                'substring' => 'Substring (supports stems)',
                'whole_word' => 'Whole word / phrase',
            ])->default('substring'),
            Text::make('Negative words', 'negative_words')
                ->hint('Comma or newline separated; a match suppresses the lead.'),
            Text::make('Score', 'score')->default(10),
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
            Select::make('Match mode', 'match_mode')->options([
                'substring' => 'Substring (supports stems)',
                'whole_word' => 'Whole word / phrase',
            ]),
            Text::make('Negative words', 'negative_words'),
            Text::make('Score', 'score'),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToActiveWorkspace($builder);
    }

    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToActiveWorkspace($builder);
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Word', 'word')->placeholder('Search keyword'),
            Select::make('Type', 'type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
                'both' => 'Both',
            ])->nullable(),
            Select::make('Match mode', 'match_mode')->options([
                'substring' => 'Substring (supports stems)',
                'whole_word' => 'Whole word / phrase',
            ])->nullable(),
        ];
    }
}
