<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Workspace;

use App\Models\AdminUser;
use App\Models\Workspace;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\Workspace\Pages\WorkspaceIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Ability;
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
            Text::make('Current', 'id')->changePreview(
                static fn (mixed $id): string => session('workspace_id') === (int) $id ? 'Current' : '—',
            ),
            BelongsTo::make('Owner', 'owner', resource: MoonShineUserResource::class),
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            WorkspaceIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name')->required(),
            BelongsTo::make('Owner', 'owner', resource: MoonShineUserResource::class)
                ->required()
                ->canSee(fn (): bool => $this->isSystemAdmin()),
            BelongsToMany::make('Members', 'members', resource: MoonShineUserResource::class)
                ->fields([
                    Select::make('Role', 'role')->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ])->default('member'),
                ])
                ->canSee(fn (): bool => $this->isSystemAdmin()),
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

    #[AsyncMethod]
    public function activate(): void
    {
        $workspace = $this->availableWorkspaces()->find((int) request('resourceItem'));

        abort_if($workspace === null, 404);

        session(['workspace_id' => $workspace->id]);
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToCurrentUser($builder);
    }

    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToCurrentUser($builder);
    }

    protected function isCan(Ability $ability): bool
    {
        $user = auth('moonshine')->user();

        if (! $user instanceof AdminUser || $user->isSystemAdmin()) {
            return parent::isCan($ability);
        }

        if ($ability === Ability::VIEW_ANY) {
            return true;
        }

        if ($ability === Ability::CREATE) {
            return false;
        }

        $workspace = $this->getItem()?->getOriginal();

        return $workspace instanceof Workspace && $user->canManageWorkspace($workspace);
    }

    private function availableWorkspaces(): Builder
    {
        return $this->scopeToCurrentUser(Workspace::query());
    }

    private function scopeToCurrentUser(Builder $builder): Builder
    {
        $user = auth('moonshine')->user();

        if (! $user instanceof AdminUser || $user->isSystemAdmin()) {
            return $builder;
        }

        return $builder->whereHas('members', static fn (Builder $query): Builder => $query->whereKey($user->id));
    }

    private function isSystemAdmin(): bool
    {
        return auth('moonshine')->user() instanceof AdminUser
            && auth('moonshine')->user()->isSystemAdmin();
    }
}
