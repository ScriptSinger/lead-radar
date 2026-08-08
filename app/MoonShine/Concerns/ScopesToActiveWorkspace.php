<?php

namespace App\MoonShine\Concerns;

use App\Support\WorkspaceContext;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait ScopesToActiveWorkspace
{
    protected function scopeToActiveWorkspace(Builder $builder): Builder
    {
        $workspaceId = WorkspaceContext::id();

        abort_if($workspaceId === null, 403, 'Select a workspace first.');

        return $builder->where('workspace_id', $workspaceId);
    }
}
