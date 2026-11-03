<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter limit specifically for auth endpoints to slow down
        // brute-force login/registration attempts.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Public passport lookup — unauthenticated endpoint. Strict rate
        // limit per IP to slow down enumeration/scraping attempts.
        // 10 requests per minute means a manual lookup is comfortable
        // but automated scanning of passport numbers is impractical.
        RateLimiter::for('lookup', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        Schema::defaultStringLength(191);
    }
}