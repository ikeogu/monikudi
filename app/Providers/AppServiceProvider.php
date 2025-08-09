<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;


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
        Vite::prefetch(concurrency: 3);

        /* if (config('app.env') === 'production') {
            URL::forceScheme('https');
        } */

        // Alternative: Force HTTPS when behind a proxy (like Render)
        /* if (request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        } */
    }
}
