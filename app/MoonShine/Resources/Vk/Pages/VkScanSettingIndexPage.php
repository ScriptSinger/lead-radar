<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Vk\Pages;

use App\MoonShine\Resources\Vk\VkScanSettingResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

/**
 * @extends IndexPage<VkScanSettingResource>
 */
class VkScanSettingIndexPage extends IndexPage
{
    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()
            ->prepend(
                ActionButton::make('Run scan now')
                    ->name('scan-settings-run-now')
                    ->icon('play')
                    ->primary()
                    ->method(
                        method: 'runNow',
                        message: 'Scan wave queued',
                    ),
            );
    }
}
