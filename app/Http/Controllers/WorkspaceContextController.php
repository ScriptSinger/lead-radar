<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceContextController extends Controller
{
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = auth('moonshine')->user();

        abort_unless($user instanceof AdminUser && $user->workspaces()->whereKey($workspace->id)->exists(), 403);

        $request->session()->put('workspace_id', $workspace->id);

        return back();
    }
}
