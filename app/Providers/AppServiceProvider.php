<?php

namespace App\Providers;

use App\Services\Chat\AIRouter;
use App\Services\Chat\BlockPresenter;
use App\Services\Chat\TemplateRenderer;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenRouterClient::class, fn () => OpenRouterClient::fromConfig());
        $this->app->singleton(BlockPresenter::class, function () {
            return new BlockPresenter(
                $this->app->make(TemplateRenderer::class),
                (int) config('chat.cache_ttl_seconds', 300)
            );
        });
        $this->app->singleton(AIRouter::class, function () {
            return new AIRouter(
                $this->app->make(OpenRouterClient::class),
                config('chat.fallback_block_key', 'main_menu')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production so asset URLs (Vite) and links use https (avoids Mixed Content behind proxies like Railway).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
