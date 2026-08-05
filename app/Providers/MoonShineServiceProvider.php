<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\ScanRun\ScanRunResource;
use App\MoonShine\Resources\ScanSetting\ScanSettingResource;
use App\MoonShine\Resources\TelegramChannelResource;
use App\MoonShine\Resources\TelegramPostResource;
use App\MoonShine\Resources\TelegramScanRunResource;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
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
                TelegramPostResource::class,
                TelegramScanRunResource::class,
                KeywordResource::class,
                LeadResource::class,
                ScanRunResource::class,
                ScanSettingResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
            ]);
    }
}
