<?php

namespace App\Models;

use App\Enums\WorkspaceMemberRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

class AdminUser extends MoonshineUser
{
    protected $table = 'moonshine_users';

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user', 'user_id', 'workspace_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isSystemAdmin(): bool
    {
        return $this->moonshine_user_role_id === MoonshineUserRole::DEFAULT_ROLE_ID;
    }

    public function canManageWorkspace(Workspace $workspace): bool
    {
        return $this->isSystemAdmin() || $this->workspaces()
            ->whereKey($workspace->id)
            ->wherePivotIn('role', [
                WorkspaceMemberRole::Owner->value,
                WorkspaceMemberRole::Admin->value,
            ])
            ->exists();
    }
}
