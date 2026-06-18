<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Keyword;

use App\Models\Keyword;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

class KeywordResource extends ModelResource
{
    protected string $model = Keyword::class;

    protected string $title = 'Keywords';

    public function fields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Word'),
            Select::make('Type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
                'both' => 'Both',
            ]),
            HasMany::make('Leads', 'leads'),
        ];
    }
}
