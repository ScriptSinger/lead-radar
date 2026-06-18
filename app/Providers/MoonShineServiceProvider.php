<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\LeadResource;

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
                KeywordResource::class,
                LeadResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
            ])
        ;
    }
}
