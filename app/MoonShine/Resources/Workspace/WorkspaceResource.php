<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Workspace;

use App\Models\Workspace;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Workspace>
 */
#[Icon('building-office')]
class WorkspaceResource extends ModelResource
{
    protected string $model = Workspace::class;

    protected string $title = 'Workspaces';

    protected string $column = 'name';

    protected string $sortColumn = 'name';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Text::make('Slug', 'slug')->sortable(),
            BelongsTo::make('Owner', 'owner', resource: MoonShineUserResource::class),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name')->required(),
            BelongsTo::make('Owner', 'owner', resource: MoonShineUserResource::class)->required(),
            BelongsToMany::make('Members', 'members', resource: MoonShineUserResource::class)
                ->fields([
                    Select::make('Role', 'role')->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ])->default('member'),
                ]),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name'),
            Text::make('Slug', 'slug'),
            BelongsTo::make('Owner', 'owner', resource: MoonShineUserResource::class),
            BelongsToMany::make('Members', 'members', resource: MoonShineUserResource::class)
                ->fields([
                    Select::make('Role', 'role')->options([
                        'owner' => 'Owner',
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ]),
                ]),
        ];
    }

    /**
     * @param  DataWrapperContract<Workspace>  $item
     * @return DataWrapperContract<Workspace>
     */
    protected function afterCreated(DataWrapperContract $item): DataWrapperContract
    {
        $workspace = $item->getOriginal();

        $workspace->members()->syncWithoutDetaching([
            $workspace->owner_id => ['role' => 'owner'],
        ]);

        return $item;
    }
}
