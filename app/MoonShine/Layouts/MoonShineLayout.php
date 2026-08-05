<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\ScanRun\ScanRunResource;
use App\MoonShine\Resources\ScanSetting\ScanSettingResource;
use App\MoonShine\Resources\TelegramChannelResource;
use App\MoonShine\Resources\TelegramPostResource;
use App\MoonShine\Resources\TelegramScanRunResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
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
        return [
            ...parent::menu(),

            // Core lead pipeline (source-agnostic)
            MenuGroup::make('Leads', [
                MenuItem::make(LeadResource::class, 'Leads'),
                MenuItem::make(KeywordResource::class, 'Keywords'),
            ], 'bolt'),

            // VK channel — keep all VK-only screens here as the project grows
            MenuGroup::make('VK', [
                MenuItem::make(VkGroupResource::class, 'Groups'),
                MenuItem::make(VkPostResource::class, 'Posts'),
            ], 'globe-alt'),

            MenuGroup::make('Telegram', [
                MenuItem::make(TelegramChannelResource::class, 'Channels'),
                MenuItem::make(TelegramPostResource::class, 'Posts'),
                MenuItem::make(TelegramScanRunResource::class, 'Scan Runs'),
            ], 'paper-airplane'),

            // Scan orchestration / observability
            MenuGroup::make('Scanning', [
                MenuItem::make(ScanRunResource::class, 'Scan Runs'),
                MenuItem::make(ScanSettingResource::class, 'Scan Settings'),
            ], 'arrow-path'),
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
