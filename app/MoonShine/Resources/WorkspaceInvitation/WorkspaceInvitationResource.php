<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WorkspaceInvitation;

use App\Enums\WorkspaceMemberRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\AdminUser;
use App\Models\WorkspaceInvitation;
use App\MoonShine\Resources\Workspace\WorkspaceResource;
use App\MoonShine\Resources\WorkspaceInvitation\Pages\WorkspaceInvitationIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<WorkspaceInvitation>
 */
#[Icon('envelope')]
class WorkspaceInvitationResource extends ModelResource
{
    protected string $model = WorkspaceInvitation::class;

    protected string $title = 'Invite users';

    protected string $column = 'email';

    protected string $sortColumn = 'created_at';

    protected SortDirection $sortDirection = SortDirection::DESC;

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Workspace', 'workspace', resource: WorkspaceResource::class),
            Email::make('Email', 'email')->sortable(),
            $this->roleField(),
            Text::make('Status', 'status')->sortable(),
            Date::make('Expires', 'expires_at')->format('Y-m-d H:i')->sortable(),
            Date::make('Accepted', 'accepted_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            WorkspaceInvitationIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Workspace', 'workspace', resource: WorkspaceResource::class)
                ->required(),
            Email::make('Email', 'email')->required(),
            $this->roleField()->default(WorkspaceMemberRole::Member->value),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Workspace', 'workspace', resource: WorkspaceResource::class),
            Email::make('Email', 'email'),
            $this->roleField(),
            Date::make('Expires', 'expires_at')->format('Y-m-d H:i:s'),
            Date::make('Accepted', 'accepted_at')->format('Y-m-d H:i:s'),
            Text::make('Invited by', 'invited_by'),
        ];
    }

    /**
     * @param  DataWrapperContract<WorkspaceInvitation>  $item
     * @return DataWrapperContract<WorkspaceInvitation>
     */
    protected function beforeCreating(DataWrapperContract $item): DataWrapperContract
    {
        abort_unless($this->isSystemAdmin(), 403);

        $invitation = $item->getOriginal();
        $invitation->plainToken = Str::random(64);
        $invitation->token = hash('sha256', $invitation->plainToken);
        $invitation->expires_at = now()->addHours(max(1, (int) config('workspaces.invitation_expiration_hours')));
        $invitation->invited_by = auth('moonshine')->id();

        return $item;
    }

    /**
     * @param  DataWrapperContract<WorkspaceInvitation>  $item
     * @return DataWrapperContract<WorkspaceInvitation>
     */
    protected function afterCreated(DataWrapperContract $item): DataWrapperContract
    {
        $invitation = $item->getOriginal();

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation, $invitation->plainToken));

        return $item;
    }

    #[AsyncMethod]
    public function resend(): void
    {
        abort_unless($this->isSystemAdmin(), 403);

        $invitation = WorkspaceInvitation::query()->find((int) request('resourceItem'));

        if ($invitation === null || $invitation->accepted_at !== null) {
            toast('Invitation cannot be resent', ToastType::ERROR);

            return;
        }

        $invitation->plainToken = Str::random(64);
        $invitation->forceFill([
            'token' => hash('sha256', $invitation->plainToken),
            'expires_at' => now()->addHours(max(1, (int) config('workspaces.invitation_expiration_hours'))),
        ])->save();

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation, $invitation->plainToken));
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
        return $this->isSystemAdmin() && parent::isCan($ability);
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        return [
            WorkspaceMemberRole::Owner->value => 'Owner',
            WorkspaceMemberRole::Admin->value => 'Admin',
            WorkspaceMemberRole::Member->value => 'Member',
        ];
    }

    private function roleField(): Select
    {
        return Select::make('Role', 'role')
            ->options($this->roleOptions())
            ->modifyRawValue(static fn (mixed $role): mixed => $role instanceof WorkspaceMemberRole ? $role->value : $role);
    }

    private function scopeToCurrentUser(Builder $builder): Builder
    {
        return $this->isSystemAdmin() ? $builder : $builder->whereRaw('1 = 0');
    }

    private function isSystemAdmin(): bool
    {
        $user = auth('moonshine')->user();

        return $user instanceof AdminUser && $user->isSystemAdmin();
    }
}
