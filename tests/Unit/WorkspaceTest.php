<?php

namespace Tests\Unit;

use App\Enums\WorkspaceMemberRole;
use App\Models\AdminUser;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_has_owner_and_members(): void
    {
        $owner = AdminUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);
        $member = AdminUser::query()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::createFor($owner, 'Lead Radar');

        $workspace->members()->attach($member, ['role' => WorkspaceMemberRole::Member->value]);

        $this->assertTrue($workspace->owner->is($owner));
        $this->assertSame('lead-radar', $workspace->slug);
        $this->assertTrue($owner->ownedWorkspaces->contains($workspace));
        $this->assertTrue($member->workspaces->contains($workspace));
        $this->assertSame(WorkspaceMemberRole::Owner->value, $workspace->members->firstWhere('id', $owner->id)?->pivot->role);
        $this->assertTrue($workspace->administrators->contains($owner));
        $this->assertFalse($workspace->administrators->contains($member));
    }
}
