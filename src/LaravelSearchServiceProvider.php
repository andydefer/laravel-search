<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch;

use Illuminate\Support\ServiceProvider;

class LaravelSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrement des services
    }

    public function boot(): void
    {
        // Publication des fichiers de configuration
        $this->publishes([
            __DIR__.'/../config/search.php' => config_path('search.php'),
        ], 'search-config');

        // Publication des migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'search-migrations');
    }
}
