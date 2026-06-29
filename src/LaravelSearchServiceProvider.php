<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch;

use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Repositories\SearchIndexRepositoryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\CandidatesFinderInterface;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchableModelDiscoveryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchIndexInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Contracts\Services\WordVectorParserInterface;
use AndyDefer\LaravelSearch\Directives\IndexDirective;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\CandidatesFinderService;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\SearchableModelDiscoveryService;
use AndyDefer\LaravelSearch\Services\SearchIndexService;
use AndyDefer\LaravelSearch\Services\SearchService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class LaravelSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/search.php',
            'search'
        );

        // Bind interfaces
        $this->app->bind(SearchConfigInterface::class, SearchConfig::class);
        $this->app->bind(TextNormalizerInterface::class, TextNormalizerService::class);
        $this->app->bind(NgramInterface::class, NgramService::class);
        $this->app->bind(WordVectorParserInterface::class, WordVectorParserService::class);
        $this->app->bind(QueryProcessorInterface::class, QueryProcessorService::class);
        $this->app->bind(SearchIndexRepositoryInterface::class, SearchIndexRepository::class);
        $this->app->bind(CandidatesFinderInterface::class, CandidatesFinderService::class);
        $this->app->bind(SearchInterface::class, SearchService::class);
        $this->app->bind(SearchIndexInterface::class, SearchIndexService::class);
        $this->app->bind(SearchableModelDiscoveryInterface::class, SearchableModelDiscoveryService::class);
        $this->app->bind(FileSystemInterface::class, FileSystemService::class);

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

        $this->app->singleton(WordVectorParserService::class, function ($app) {
            return new WordVectorParserService(
                $app->make(TextNormalizerService::class),
                $app->make(NgramService::class)
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
                $app->make(NgramService::class),
                $app->make(WordVectorParserService::class),
                $app->make(SearchConfig::class),
            );
        });

        $this->app->singleton(CandidatesFinderService::class, function ($app) {
            return new CandidatesFinderService(
                $app->make(SearchIndexRepository::class),
                $app->make(TextNormalizerService::class),
                $app->make(NgramService::class),
                $app->make(QueryProcessorService::class),
                $app->make(WordVectorParserService::class),
                $app->make(SearchConfig::class)
            );
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService(
                $app->make(SearchIndexRepository::class),
                $app->make(QueryProcessorService::class),
                $app->make(SearchConfig::class),
                $app->make(TextNormalizerService::class),
                $app->make(CandidatesFinderService::class)
            );
        });

        $this->app->singleton(SearchIndexService::class, function ($app) {
            return new SearchIndexService(
                $app->make(SearchIndexRepository::class),
                $app->make(TextNormalizerService::class),
                $app->make(NgramService::class),
                $app->make(WordVectorParserService::class)
            );
        });

        $this->app->singleton(SearchableModelDiscoveryService::class, function ($app) {
            return new SearchableModelDiscoveryService(
                $app->make(FileSystemInterface::class),
                $app->make(SearchConfig::class)
            );
        });

        // Directives

    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/search.php' => config_path('search.php'),
        ], 'search-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'search-migrations');

        // Enregistrer les directives
        if ($this->app->has('directive')) {
            $this->app->make('directive')->register(IndexDirective::class);
        }
    }
}
