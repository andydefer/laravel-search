<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelSearch\Contracts\Services\SearchableModelDiscoveryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchIndexInterface;

final class IndexDirective extends AbstractDirective
{
    private Console $console;

    public function getSignature(): string
    {
        return 'search:index {morph-class?} {--batch=100} {--all} {--sync} {--reindex}';
    }

    public function getDescription(): string
    {
        return 'Indexe les modèles Searchable';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('search:reindex');
        $aliases->add('search:sync');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    protected function execute(): ExitCode
    {
        $this->console = new Console;

        $app = $this->getLaravel();

        /** @var SearchIndexInterface $searchIndex */
        $searchIndex = $app->make(SearchIndexInterface::class);

        /** @var SearchableModelDiscoveryInterface $discovery */
        $discovery = $app->make(SearchableModelDiscoveryInterface::class);

        $morphClass = $this->argument('morph-class');
        $batchSize = (int) ($this->option('batch') ?? 100);
        $all = $this->option('all') ?? false;
        $sync = $this->option('sync') ?? false;
        $reindex = $this->option('reindex') ?? false;

        if ($all) {
            return $this->indexAll($searchIndex, $discovery, $batchSize, $sync, $reindex);
        }

        if ($morphClass !== null) {
            return $this->indexSingle($searchIndex, $discovery, $morphClass, $batchSize, $sync, $reindex);
        }

        $this->console
            ->error('Veuillez spécifier une classe ou utiliser --all')
            ->newLine()
            ->line('Usage:')
            ->line('  ./directive search:index App\\Models\\User')
            ->line('  ./directive search:index --all')
            ->line('  ./directive search:index --all --sync')
            ->line('  ./directive search:index --all --reindex')
            ->render();

        return ExitCode::INVALID_ARGUMENT;
    }

    private function indexSingle(
        SearchIndexInterface $searchIndex,
        SearchableModelDiscoveryInterface $discovery,
        string $morphClass,
        int $batchSize,
        bool $sync,
        bool $reindex
    ): ExitCode {
        if (! class_exists($morphClass)) {
            $this->console
                ->error("La classe '{$morphClass}' n'existe pas")
                ->render();

            return ExitCode::INVALID_ARGUMENT;
        }

        if (! $discovery->isSearchable($morphClass)) {
            $this->console
                ->error("La classe '{$morphClass}' n'implémente pas Searchable")
                ->render();

            return ExitCode::INVALID_ARGUMENT;
        }

        $this->console->info("📦 Indexation de: {$morphClass}")->render();

        if ($sync) {
            $this->console->info('🔄 Mode synchronisation')->render();
            $result = $searchIndex->sync($morphClass, $batchSize);

            $this->console
                ->newLine()
                ->info('📊 Résultats:')
                ->keyValueWithValueColor(MapCollection::from([
                    '✅ Indexés' => (string) $result->indexed,
                    '❌ Supprimés' => (string) $result->deleted,
                    '⏭️ Ignorés' => (string) $result->skipped,
                    '📊 Total' => (string) $result->total,
                ]), 'cyan')
                ->render();

            return ExitCode::SUCCESS;
        }

        if ($reindex) {
            $this->console->info('🔄 Mode réindexation')->render();
            $count = $searchIndex->reindexAll($morphClass, $batchSize);

            $this->console
                ->newLine()
                ->success("✅ Réindexation terminée: {$count} entités indexées")
                ->render();

            return ExitCode::SUCCESS;
        }

        $count = $searchIndex->indexAll($morphClass, $batchSize);

        $this->console
            ->newLine()
            ->success("✅ Indexation terminée: {$count} entités indexées")
            ->render();

        return ExitCode::SUCCESS;
    }

    private function indexAll(
        SearchIndexInterface $searchIndex,
        SearchableModelDiscoveryInterface $discovery,
        int $batchSize,
        bool $sync,
        bool $reindex
    ): ExitCode {
        $models = $discovery->discover();

        if ($models->isEmpty()) {
            $this->console
                ->alertWarning('Aucun modèle Searchable trouvé')
                ->render();

            return ExitCode::SUCCESS;
        }

        $this->console
            ->info('📦 Découverte de '.$models->count().' modèles Searchable')
            ->newLine()
            ->render();

        $totalIndexed = 0;
        $totalDeleted = 0;
        $totalSkipped = 0;
        $errors = [];

        foreach ($models as $className => $path) {
            $this->console->line("  🔍 Traitement de: {$className}")->render();

            try {
                if ($sync) {
                    $result = $searchIndex->sync($className, $batchSize);
                    $totalIndexed += $result->indexed;
                    $totalDeleted += $result->deleted;
                    $totalSkipped += $result->skipped;

                    $this->console
                        ->line("    ✅ Indexés: {$result->indexed}, Supprimés: {$result->deleted}, Ignorés: {$result->skipped}")
                        ->render();
                } elseif ($reindex) {
                    $count = $searchIndex->reindexAll($className, $batchSize);
                    $totalIndexed += $count;

                    $this->console
                        ->line("    ✅ Réindexés: {$count}")
                        ->render();
                } else {
                    $count = $searchIndex->indexAll($className, $batchSize);
                    $totalIndexed += $count;

                    $this->console
                        ->line("    ✅ Indexés: {$count}")
                        ->render();
                }
            } catch (\Exception $e) {
                $errors[] = $className.': '.$e->getMessage();

                $this->console
                    ->error('    ❌ Erreur: '.$e->getMessage())
                    ->render();
            }
        }

        $this->console
            ->newLine()
            ->line(str_repeat('=', 80))
            ->info('📊 Résumé global:')
            ->render();

        if ($sync) {
            $this->console
                ->keyValueWithValueColor(MapCollection::from([
                    '✅ Indexés' => (string) $totalIndexed,
                    '❌ Supprimés' => (string) $totalDeleted,
                    '⏭️ Ignorés' => (string) $totalSkipped,
                    '📊 Total' => (string) ($totalIndexed + $totalDeleted + $totalSkipped),
                ]), 'cyan')
                ->render();
        } else {
            $this->console
                ->keyValueWithValueColor(MapCollection::from([
                    '✅ Total indexés' => (string) $totalIndexed,
                ]), 'green')
                ->render();
        }

        if (! empty($errors)) {
            $this->console
                ->newLine()
                ->error('❌ Erreurs rencontrées:')
                ->render();

            foreach ($errors as $error) {
                $this->console->line("  - {$error}")->render();
            }

            return ExitCode::FAILURE;
        }

        $this->console
            ->line(str_repeat('=', 80))
            ->success('✅ Toutes les indexations sont terminées avec succès!')
            ->render();

        return ExitCode::SUCCESS;
    }
}
