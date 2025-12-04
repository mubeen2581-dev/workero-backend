<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->configureRateLimiting();
        $this->loadRoutes();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Increased limit for development: 1000 requests per minute
            // In production, you may want to reduce this to 60 or 120
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Load application routes.
     */
    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/web.php'));
            
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }
}

