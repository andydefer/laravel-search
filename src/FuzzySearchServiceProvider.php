<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchEngineServiceInterface;
use AndyDefer\LaravelSearch\Contexts\NormalizerContext;
use AndyDefer\LaravelSearch\Contexts\SearchEngineConfigContext;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\IndexService;
use AndyDefer\LaravelSearch\Services\NormalizerService;
use AndyDefer\LaravelSearch\Services\SearchEngineService;
use AndyDefer\LaravelSearch\Services\SearchService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class FuzzySearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fuzzy-search.php', 'fuzzy-search');

        // Configuration
        $this->app->singleton(SearchConfigInterface::class, function ($app) {
            return new SearchConfig(
                config: $app->make(ConfigRepository::class),
            );
        });

        // Contextes (état mutable)
        $this->app->singleton(NormalizerContext::class, function () {
            return new NormalizerContext();
        });

        $this->app->singleton(SearchEngineConfigContext::class, function ($app) {
            $config = $app->make(SearchConfigInterface::class);
            return new SearchEngineConfigContext(
                engineConfig: $config->getEngine(),
            );
        });

        // Services stateless
        $this->app->singleton(HydrationService::class, function () {
            return new HydrationService();
        });

        $this->app->singleton(NormalizerService::class, function ($app) {
            return new NormalizerService(
                context: $app->make(NormalizerContext::class),
            );
        });

        $this->app->singleton(SearchIndexRepository::class, function () {
            return new SearchIndexRepository();
        });

        $this->app->singleton(SearchEngineServiceInterface::class, function ($app) {
            return new SearchEngineService(
                engineConfigContext: $app->make(SearchEngineConfigContext::class),
                config: $app->make(SearchConfigInterface::class),
                cache: $app->make(JsonlCacheInterface::class),
            );
        });

        $this->app->singleton(IndexService::class, function ($app) {
            return new IndexService(
                config: $app->make(SearchConfigInterface::class),
                repository: $app->make(SearchIndexRepository::class),
                normalizer: $app->make(NormalizerService::class),
                hydration: $app->make(HydrationService::class),
            );
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService(
                config: $app->make(SearchConfigInterface::class),
                repository: $app->make(SearchIndexRepository::class),
                engine: $app->make(SearchEngineServiceInterface::class),
                cache: $app->make(JsonlCacheInterface::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/fuzzy-search.php' => config_path('fuzzy-search.php'),
            ], 'fuzzy-search-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'fuzzy-search-migrations');
        }
    }
}
