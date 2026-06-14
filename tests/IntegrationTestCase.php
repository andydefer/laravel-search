<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\JsonlCache\JsonlCacheServiceProvider;
use AndyDefer\LaravelSearch\FuzzySearchServiceProvider;
use AndyDefer\PhpServices\PhpServiceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configuration par défaut pour les tests AVANT de démarrer l'application
        $this->app['config']->set('jsonl-cache', [
            'base_path' => sys_get_temp_dir() . '/jsonl_cache_test_' . uniqid(),
            'default_ttl' => 3600,
            'hash_levels' => 2,
            'enabled' => true,
            'prefix' => 'test_',
        ]);

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function getPackageProviders($app): array
    {
        return [
            DirectiveServiceProvider::class,
            PhpServiceServiceProvider::class,
            JsonlCacheServiceProvider::class,
            FuzzySearchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function runMigrations(): void
    {
        $migrationPaths = [
            __DIR__ . '/../src/Migrations',
            __DIR__ . '/Fixtures/database/migrations',
        ];

        foreach ($migrationPaths as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
