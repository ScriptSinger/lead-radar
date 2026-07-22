<?php

namespace App\Providers;

use App\Models\Keyword;
use App\Models\Lead;
use App\Observers\KeywordObserver;
use App\Observers\LeadObserver;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramNotifier::class, function ($app) {
            return new TelegramNotifier($app->make(Api::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Keyword::observe(KeywordObserver::class);
        Lead::observe(LeadObserver::class);
    }
}
