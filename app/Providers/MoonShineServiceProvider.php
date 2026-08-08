<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\Telegram\TelegramChannelResource;
use App\MoonShine\Resources\Telegram\TelegramCommentResource;
use App\MoonShine\Resources\Telegram\TelegramPostResource;
use App\MoonShine\Resources\Telegram\TelegramScanRunResource;
use App\MoonShine\Resources\Telegram\TelegramScanSettingResource;
use App\MoonShine\Resources\Vk\VkCommentResource;
use App\MoonShine\Resources\Vk\VkGroupResource;
use App\MoonShine\Resources\Vk\VkPostResource;
use App\MoonShine\Resources\Vk\VkScanRunResource;
use App\MoonShine\Resources\Vk\VkScanSettingResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                VkGroupResource::class,
                VkPostResource::class,
                VkCommentResource::class,
                TelegramChannelResource::class,
                TelegramCommentResource::class,
                TelegramPostResource::class,
                TelegramScanRunResource::class,
                TelegramScanSettingResource::class,
                KeywordResource::class,
                LeadResource::class,
                VkScanRunResource::class,
                VkScanSettingResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
            ]);
    }
}
