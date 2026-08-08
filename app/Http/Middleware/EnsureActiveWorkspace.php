<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveWorkspace
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('moonshine')->user();

        if (! $user instanceof AdminUser) {
            return $next($request);
        }

        $workspaceId = $request->session()->get('workspace_id');

        if ($workspaceId !== null && $user->workspaces()->whereKey($workspaceId)->exists()) {
            return $next($request);
        }

        $firstWorkspaceId = $user->workspaces()->value('workspaces.id');

        if ($firstWorkspaceId === null) {
            $request->session()->forget('workspace_id');
        } else {
            $request->session()->put('workspace_id', $firstWorkspaceId);
        }

        return $next($request);
    }
}
