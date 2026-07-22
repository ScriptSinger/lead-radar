<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkGroup;

use App\Models\VkGroup;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

class VkGroupResource extends ModelResource
{
    protected string $model = VkGroup::class;

    protected string $title = 'VK Groups';

    /** Shown in BelongsTo labels on related resources */
    protected string $column = 'name';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name'),
            Url::make('Url', 'url'),
            Switcher::make('Active', 'active'),
        ];
    }

    protected function formFields(): iterable
    {
        return [

            Text::make('Name', 'name')->required(),
            Url::make('Url', 'url')->required(),
            Switcher::make('Active', 'active'),
            Date::make('Last scan', 'last_scan_at')
                ->withTime()
                ->format('Y-m-d H:i:s'),

        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name'),
            Url::make('Url', 'url'),
            Switcher::make('Active', 'active'),
            Date::make('Last scan', 'last_scan_at')->format('Y-m-d H:i:s'),
            Date::make('Created at', 'created_at')->format('Y-m-d H:i:s'),
            Date::make('Updated at', 'updated_at')->format('Y-m-d H:i:s'),
        ];
    }
}
