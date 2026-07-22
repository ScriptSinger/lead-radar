<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use MoonShine\MenuManager\MenuItem;
use App\MoonShine\Resources\VkPost\VkPostResource;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\ScanRun\ScanRunResource;
use App\MoonShine\Resources\ScanSetting\ScanSettingResource;

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
            MenuItem::make(VkGroupResource::class, 'VK Groups'),
            MenuItem::make(VkPostResource::class, 'VK Posts'),
            MenuItem::make(VkCommentResource::class, 'VK Comments'),
            MenuItem::make(KeywordResource::class, 'Keywords'),
            MenuItem::make(LeadResource::class, 'Leads'),
            MenuItem::make(ScanRunResource::class, 'Scan Runs'),
            MenuItem::make(ScanSettingResource::class, 'Scan Settings'),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
