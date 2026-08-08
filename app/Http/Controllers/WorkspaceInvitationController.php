<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceMemberRole;
use App\Models\AdminUser;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use MoonShine\Laravel\Models\MoonshineUserRole;

class WorkspaceInvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);

        return view('workspace-invitations.accept', [
            'invitation' => $invitation,
            'hasAccount' => AdminUser::query()->where('email', $invitation->email)->exists(),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $hasAccount = AdminUser::query()->where('email', $invitation->email)->exists();

        $data = $request->validate([
            'name' => [$hasAccount ? 'nullable' : 'required', 'string', 'max:255'],
            'password' => [$hasAccount ? 'nullable' : 'required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($data, $token): void {
            $invitation = WorkspaceInvitation::query()
                ->where('token', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($invitation->isUsable(), 404);

            $user = AdminUser::query()->where('email', $invitation->email)->first();

            if ($user === null) {
                $user = AdminUser::query()->create([
                    'email' => $invitation->email,
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'moonshine_user_role_id' => MoonshineUserRole::query()
                        ->where('name', 'Client')
                        ->sole()
                        ->id,
                ]);
            }

            $role = WorkspaceMemberRole::from($invitation->role);

            $invitation->workspace->members()->syncWithoutDetaching([
                $user->id => ['role' => $role->value],
            ]);

            if ($role === WorkspaceMemberRole::Owner) {
                $previousOwnerId = $invitation->workspace->owner_id;

                if ($previousOwnerId !== $user->id) {
                    $invitation->workspace->members()->updateExistingPivot($previousOwnerId, ['role' => 'admin']);
                    $invitation->workspace->forceFill(['owner_id' => $user->id])->save();
                }
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        return to_route('moonshine.login')->with('status', $hasAccount
            ? 'Invitation accepted. Sign in to continue.'
            : 'Password set. Sign in to continue.');
    }

    private function findInvitation(string $token): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($invitation->isUsable(), 404);

        return $invitation;
    }
}
