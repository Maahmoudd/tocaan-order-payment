<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('auth', fn (Request $request): Limit => $this->apiLimit(10, $request->ip()));

        RateLimiter::for('refresh', fn (Request $request): Limit => $this->apiLimit(20, $request->ip()));

        RateLimiter::for('payments', fn (Request $request): Limit => $this->apiLimit(
            10,
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }

    private function apiLimit(int $attempts, string $key): Limit
    {
        return Limit::perMinute($attempts)
            ->by($key)
            ->response(fn (): Response => response([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'errors' => new \stdClass,
            ], 429));
    }
}
