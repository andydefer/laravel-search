<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch;

use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\SearchService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class LaravelSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-search.php',
            'search'
        );

        // Services
        $this->app->singleton(SearchConfig::class, function ($app) {
            return new SearchConfig(
                $app->make(ConfigRepository::class)
            );
        });

        $this->app->singleton(TextNormalizerService::class, function ($app) {
            return new TextNormalizerService(
                $app->make(SearchConfig::class)
            );
        });

        $this->app->singleton(NgramService::class, function ($app) {
            return new NgramService(
                $app->make(SearchConfig::class),
                $app->make(TextNormalizerService::class)
            );
        });

        $this->app->singleton(QueryProcessorService::class, function ($app) {
            return new QueryProcessorService(
                $app->make(SearchConfig::class),
                $app->make(TextNormalizerService::class),
                $app->make(NgramService::class)
            );
        });

        $this->app->singleton(SearchIndexRepository::class, function ($app) {
            return new SearchIndexRepository(
                $app->make(NgramService::class)
            );
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService(
                $app->make(SearchIndexRepository::class),
                $app->make(QueryProcessorService::class),
                $app->make(SearchConfig::class),
                $app->make(TextNormalizerService::class),
                $app->make(NgramService::class)
            );
        });
    }

    public function boot(): void
    {
        // Publication des fichiers de configuration
        $this->publishes([
            __DIR__.'/../config/laravel-search.php' => config_path('laravel-search.php'),
        ], 'search-config');

        // Publication des migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'search-migrations');
    }
}
