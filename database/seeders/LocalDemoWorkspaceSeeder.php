<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Keyword;
use App\Models\ScanSetting;
use App\Models\TelegramScanSetting;
use App\Models\VkGroup;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUserRole;

class LocalDemoWorkspaceSeeder extends Seeder
{
    public const EMAIL = 'demo.client@leadradar.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('Local demo profile is only available in the local environment.');

            return;
        }

        $clientRoleId = MoonshineUserRole::query()->where('name', 'Client')->value('id');

        if ($clientRoleId === null) {
            throw new \LogicException('The Client MoonShine role is missing. Run migrations first.');
        }

        $user = AdminUser::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Demo Client',
                'password' => Hash::make(self::PASSWORD),
                'moonshine_user_role_id' => $clientRoleId,
            ],
        );

        $workspace = Workspace::query()
            ->where('owner_id', $user->id)
            ->where('slug', 'demo-client-workspace')
            ->first();

        if ($workspace === null) {
            $workspace = Workspace::createFor($user, 'Demo Client Workspace');
        }

        $workspace->members()->syncWithoutDetaching([
            $user->id => ['role' => 'owner'],
        ]);

        Keyword::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'word' => 'demo lead'],
            ['type' => 'both', 'match_mode' => 'substring', 'score' => 10],
        );
        VkGroup::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'url' => 'https://vk.com/club1'],
            ['name' => 'Demo VK group', 'active' => false],
        );
        ScanSetting::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => ScanSetting::NAME_DEFAULT],
            ScanSetting::defaultAttributes(),
        );
        TelegramScanSetting::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'default'],
            ['schedule_enabled' => false, 'interval_minutes' => 30, 'channel_delay_seconds' => 3, 'scan_limit' => 20, 'with_comments' => true, 'comments_limit' => 100],
        );

        $this->command?->info('Local demo client created: '.self::EMAIL.' / '.self::PASSWORD);
    }
}
