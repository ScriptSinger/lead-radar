<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Lead;

use App\Models\Lead;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;


class LeadResource extends ModelResource
{
    protected string $model = Lead::class;

    protected string $title = 'Leads';

    public function fields(): array
    {
        return [
            ID::make()->sortable(),
            Select::make('Source type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ]),
            BelongsTo::make('Post', 'post'),
            BelongsTo::make('Comment', 'comment'),
            BelongsTo::make('Group', 'group'),
            BelongsTo::make('Keyword', 'keyword'),
            Text::make('Text'),
            Url::make('Url'),
            Number::make('Score'),
            Select::make('Status')->options([
                'new' => 'New',
                'processed' => 'Processed',
                'ignored' => 'Ignored',
            ]),
        ];
    }
}
