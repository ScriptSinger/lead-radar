<?php

namespace App\Models;

use App\Enums\WorkspaceMemberRole;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Workspace extends Model
{
    use Sluggable;

    protected $fillable = [
        'name',
    ];

    public static function createFor(AdminUser $owner, string $name): self
    {
        return DB::transaction(function () use ($owner, $name): self {
            $workspace = new self([
                'name' => $name,
            ]);
            $workspace->owner()->associate($owner);
            $workspace->save();

            $workspace->members()->attach($owner, ['role' => WorkspaceMemberRole::Owner->value]);

            return $workspace;
        });
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'workspace_user', 'workspace_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function administrators(): BelongsToMany
    {
        return $this->members()->wherePivotIn('role', [
            WorkspaceMemberRole::Owner->value,
            WorkspaceMemberRole::Admin->value,
        ]);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }
}
