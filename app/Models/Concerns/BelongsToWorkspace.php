<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('workspace_id') !== null) {
                return;
            }

            $workspaceId = WorkspaceContext::id();

            if ($workspaceId === null) {
                throw new LogicException('A workspace must be selected before creating workspace data.');
            }

            $model->setAttribute('workspace_id', $workspaceId);
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
