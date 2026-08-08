<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\Models\AdminUser;
use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\Telegram\TelegramChannelResource;
use App\MoonShine\Resources\Telegram\TelegramCommentResource;
use App\MoonShine\Resources\Telegram\TelegramPostResource;
use App\MoonShine\Resources\Telegram\TelegramScanRunResource;
use App\MoonShine\Resources\Telegram\TelegramScanSettingResource;
use App\MoonShine\Resources\Vk\VkGroupResource;
use App\MoonShine\Resources\Vk\VkPostResource;
use App\MoonShine\Resources\Vk\VkScanRunResource;
use App\MoonShine\Resources\Vk\VkScanSettingResource;
use App\MoonShine\Resources\Workspace\WorkspaceResource;
use App\MoonShine\Resources\WorkspaceInvitation\WorkspaceInvitationResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        $user = auth('moonshine')->user();
        $systemMenu = $user instanceof AdminUser && $user->isSystemAdmin()
            ? parent::menu()
            : [];

        return [
            ...$systemMenu,

            MenuGroup::make('Workspace', [
                MenuItem::make(WorkspaceResource::class, 'Workspaces'),
                MenuItem::make(WorkspaceInvitationResource::class, 'Invite users'),
            ], 'building-office'),

            // Core lead pipeline (source-agnostic)
            MenuGroup::make('Leads', [
                MenuItem::make(LeadResource::class, 'Leads'),
                MenuItem::make(KeywordResource::class, 'Keywords'),
            ], 'bolt'),

            // VK channel — keep all VK-only screens here as the project grows
            MenuGroup::make('VK', [
                MenuItem::make(VkGroupResource::class, 'Groups'),
                MenuItem::make(VkPostResource::class, 'Posts'),
                MenuItem::make(VkScanRunResource::class, 'Scan Runs'),
                MenuItem::make(VkScanSettingResource::class, 'Scan Settings'),
            ], 'globe-alt'),

            MenuGroup::make('Telegram', [
                MenuItem::make(TelegramChannelResource::class, 'Channels'),
                MenuItem::make(TelegramPostResource::class, 'Posts'),
                MenuItem::make(TelegramCommentResource::class, 'Comments'),
                MenuItem::make(TelegramScanRunResource::class, 'Scan Runs'),
                MenuItem::make(TelegramScanSettingResource::class, 'Scan Settings'),
            ], 'paper-airplane'),

        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
