<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelSearch\FuzzySearchServiceProvider;
use Illuminate\Contracts\Console\Kernel;

final class FuzzySearchInstallDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'fuzzy-search-install {--force}';
    }

    public function getDescription(): string
    {
        return 'Install the fuzzy search package';
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        if (! $this->hasLaravel()) {
            $this->error('Laravel is required for installation');

            return ExitCode::FAILURE;
        }

        $force = $this->option('force') ?? false;
        $this->info('Installing Fuzzy Search Package...');
        $this->separator();

        $this->info('Publishing configuration...');
        $publishConfig = $this->publishConfig($force);

        if (! $publishConfig) {
            $this->error('Failed to publish configuration');

            return ExitCode::FAILURE;
        }

        $this->info('Publishing migrations...');
        $publishMigrations = $this->publishMigrations($force);

        if (! $publishMigrations) {
            $this->error('Failed to publish migrations');

            return ExitCode::FAILURE;
        }

        $this->info('Running migrations...');
        $migrated = $this->runMigrations();

        if (! $migrated) {
            $this->error('Failed to run migrations');

            return ExitCode::FAILURE;
        }

        $this->separator();
        $this->info('✓ Fuzzy Search Package installed successfully!');

        return ExitCode::SUCCESS;
    }

    private function publishConfig(bool $force): bool
    {
        try {
            $this->callLaravelCommand('vendor:publish', [
                '--provider' => FuzzySearchServiceProvider::class,
                '--tag' => 'fuzzy-search-config',
                '--force' => $force,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }
    }

    private function publishMigrations(bool $force): bool
    {
        try {
            $this->callLaravelCommand('vendor:publish', [
                '--provider' => FuzzySearchServiceProvider::class,
                '--tag' => 'fuzzy-search-migrations',
                '--force' => $force,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }
    }

    private function runMigrations(): bool
    {
        try {
            $this->callLaravelCommand('migrate');

            return true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }
    }

    private function callLaravelCommand(string $command, array $parameters = []): void
    {
        $artisan = $this->getLaravel()->make(Kernel::class);

        $exitCode = $artisan->call($command, $parameters);

        $output = $artisan->output();
        if (! empty($output)) {
            $this->line($output);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("Command '{$command}' failed with exit code: {$exitCode}");
        }
    }
}
