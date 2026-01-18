<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         // Service législatives (déjà existant normalement)
    $this->app->singleton(ResultatsService::class, function ($app) {
        return new ResultatsService($app->make(StatsService::class));
    });

    // ✅ NOUVEAU : Service communales
    $this->app->singleton(ResultatsCommunalesService::class, function ($app) {
        return new ResultatsCommunalesService($app->make(StatsService::class));
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
