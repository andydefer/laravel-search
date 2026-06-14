<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Services\IndexService;

final class FuzzySearchIndexDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'fuzzy-search-index {models*} {--force}';
    }

    public function getDescription(): string
    {
        return 'Index searchable models. If models specified, only index those. Use --force to reindex existing entries.';
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('fs-index');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        if (! $this->hasLaravel()) {
            $this->error('Laravel is required for indexing');

            return ExitCode::FAILURE;
        }

        $this->info('Fuzzy Search Indexing...');
        $this->separator();

        try {
            $config = $this->getLaravel()->make(SearchConfigInterface::class);
            $indexService = $this->getLaravel()->make(IndexService::class);

            $modelsToIndex = $this->getVariadicArguments();
            $force = $this->option('force') ?? false;

            if ($modelsToIndex->isEmpty()) {
                $models = $config->getModels();
                $this->info('Indexing all models from config...');
            } else {
                $models = $modelsToIndex;
                $this->info('Indexing specified models: '.implode(', ', $models->toArray()));
            }

            if ($models->isEmpty()) {
                $this->warn('No models to index');

                return ExitCode::SUCCESS;
            }

            $this->info('Indexing '.$models->count().' model(s)...');

            $stats = $indexService->indexAll($models, $force, function ($model, $isNew) {
                $status = $isNew ? '✓ Indexed' : '⟳ Skipped (already indexed)';
                $this->line("  {$status}: ".get_class($model).' #'.$model->getKey());
            });

            $record = $stats->getValue();

            $this->separator();
            $this->info(sprintf(
                '✓ Indexing completed! %d indexed, %d skipped, %d errors.',
                $record->indexed,
                $record->skipped,
                $record->errors
            ));

            return ExitCode::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during indexing: '.$e->getMessage());

            return ExitCode::FAILURE;
        }
    }
}
