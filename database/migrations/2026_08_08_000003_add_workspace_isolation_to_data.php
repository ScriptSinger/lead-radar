<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'vk_groups',
        'telegram_channels',
        'keywords',
        'scan_settings',
        'telegram_scan_settings',
        'scan_runs',
        'telegram_scan_runs',
        'leads',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        $ownerId = DB::table('moonshine_users')->orderBy('id')->value('id');

        if ($ownerId !== null) {
            $workspaceId = DB::table('workspaces')->orderBy('id')->value('id');

            if ($workspaceId === null) {
                $now = now();
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'owner_id' => $ownerId,
                    'name' => 'Default workspace',
                    'slug' => 'default-workspace',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('workspace_user')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $ownerId,
                    'role' => 'owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($this->tables as $tableName) {
                DB::table($tableName)->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
            }
        }

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable(false)->change();
                $table->index('workspace_id');
            });
        }

        Schema::table('vk_groups', function (Blueprint $table): void {
            $table->dropUnique(['url']);
            $table->unique(['workspace_id', 'url']);
            $table->index(['workspace_id', 'active', 'last_scan_at']);
        });
        Schema::table('telegram_channels', function (Blueprint $table): void {
            $table->dropUnique(['url']);
            $table->dropUnique(['username']);
            $table->dropUnique(['telegram_channel_id']);
            $table->unique(['workspace_id', 'url']);
            $table->unique(['workspace_id', 'username']);
            $table->unique(['workspace_id', 'telegram_channel_id']);
            $table->index(['workspace_id', 'active', 'last_scan_at']);
        });
        Schema::table('keywords', function (Blueprint $table): void {
            $table->dropUnique(['word']);
            $table->unique(['workspace_id', 'word']);
            $table->index(['workspace_id', 'type']);
        });
        Schema::table('scan_settings', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->unique(['workspace_id', 'name']);
        });
        Schema::table('telegram_scan_settings', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->unique(['workspace_id', 'name']);
        });
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique(['dedupe_key']);
            $table->unique(['workspace_id', 'dedupe_key']);
            $table->index(['workspace_id', 'status', 'created_at']);
        });
        Schema::table('vk_posts', function (Blueprint $table): void {
            $table->dropUnique(['vk_post_id']);
            $table->unique(['group_id', 'vk_post_id']);
        });
        Schema::table('scan_runs', function (Blueprint $table): void {
            $table->index(['workspace_id', 'started_at']);
        });
        Schema::table('telegram_scan_runs', function (Blueprint $table): void {
            $table->index(['workspace_id', 'started_at']);
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('workspace_id');
            });
        }
    }
};
