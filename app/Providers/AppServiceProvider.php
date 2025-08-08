<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

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
        // === Définition du rate-limiter "api" ===
        RateLimiter::for('api', function (Request $request) {
            // 60 requêtes par minute par utilisateur ou par IP
            return Limit::perMinute(60)
                        ->by(optional($request->user())->id ?: $request->ip());
        });
        // ========================================
    }
}
