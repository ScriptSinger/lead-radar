<?php

namespace App\Providers;

use App\Models\Keyword;
use App\Observers\KeywordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Keyword::observe(KeywordObserver::class);
    }
}
