<?php

namespace Tests\Feature;

use App\Enums\WorkspaceMemberRole;
use App\Models\AdminUser;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_sets_password_and_joins_workspace(): void
    {
        $owner = AdminUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);
        $workspace = Workspace::createFor($owner, 'Lead Radar');
        $token = 'test-invitation-token';

        $invitation = new WorkspaceInvitation([
            'workspace_id' => $workspace->id,
            'email' => 'client@example.com',
            'role' => WorkspaceMemberRole::Owner->value,
            'expires_at' => now()->addDay(),
        ]);
        $invitation->token = hash('sha256', $token);
        $invitation->save();

        $this->get(route('workspace-invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Lead Radar');

        $this->post(route('workspace-invitations.accept', ['token' => $token]), [
            'name' => 'Client',
            'password' => 'client-password',
            'password_confirmation' => 'client-password',
        ])->assertRedirect(route('moonshine.login'));

        $client = AdminUser::query()->where('email', 'client@example.com')->sole();

        $this->assertTrue(Hash::check('client-password', $client->password));
        $this->assertSame('Client', $client->moonshineUserRole->name);
        $this->assertTrue($workspace->fresh()->owner->is($client));
        $this->assertSame(WorkspaceMemberRole::Owner->value, $workspace->members()->findOrFail($client->id)->pivot->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}
