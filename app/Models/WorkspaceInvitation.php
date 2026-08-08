<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * Kept only for the request that creates the invitation; never persisted.
     */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function getStatusAttribute(): string
    {
        if ($this->accepted_at !== null) {
            return 'Accepted';
        }

        return $this->expires_at->isPast() ? 'Expired' : 'Pending';
    }
}
