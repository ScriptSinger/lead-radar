<?php

namespace Tests;

use App\Models\AdminUser;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('moonshine_users') || ! Schema::hasTable('workspaces')) {
            return;
        }

        $user = AdminUser::query()->firstOrCreate(
            ['email' => 'workspace-test@example.test'],
            ['name' => 'Workspace test user', 'password' => Hash::make('password')],
        );
        $workspace = Workspace::query()->first() ?? Workspace::createFor($user, 'Test workspace');

        session(['workspace_id' => $workspace->id]);
    }
}
