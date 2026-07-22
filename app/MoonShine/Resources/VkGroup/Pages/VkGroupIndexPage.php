<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkGroup\Pages;

use App\MoonShine\Resources\VkGroup\VkGroupResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

/**
 * @extends IndexPage<VkGroupResource>
 */
class VkGroupIndexPage extends IndexPage
{
    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()
            ->prepend(
                ActionButton::make('Scan now')
                    ->name('vk-group-scan-now')
                    ->icon('arrow-path')
                    ->primary()
                    ->method(
                        method: 'scanNow',
                        message: 'Scan finished',
                    ),
            );
    }
}
