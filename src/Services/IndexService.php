<?php

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Contracts\Services\IndexServiceInterface;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\ValueObjects\IndexStatsVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;

class IndexService implements IndexServiceInterface
{
    public function __construct(
        private readonly SearchConfigInterface $config,
        private readonly SearchIndexRepository $repository,
        private readonly NormalizerService $normalizer,
        private readonly HydrationService $hydration,
    ) {}

    public function index(Searchable $model, bool $force = false): bool
    {
        if (! $force) {
            $filters = new SearchIndexFiltersRecord(
                searchable_type: $model->getMorphClass(),
                searchable_id: (string) $model->getKey(),
            );

            $findBy = new FindByRecord(
                filters: $filters,
                columns: new SelectColumns(['id']),
            );

            $existing = $this->repository->findBy($findBy)->first();

            if ($existing) {
                return false;
            }
        }

        $fields = $model->getSearchableFields();
        $protectedFields = $model->getProtectedFields();

        $normalizedFields = $this->normalizer->normalizeCollection($fields, $protectedFields);

        $content = $this->normalizer->buildContentString($fields);
        $normalizedContent = $this->normalizer->buildContentString($normalizedFields);

        /** @var SearchIndexRecord $record */
        $record = $this->hydration->hydrate(SearchIndexRecord::class, [
            'searchable_type' => $model->getMorphClass(),
            'searchable_id' => $model->getKey(),
            'content' => $content,
            'normalized_content' => $normalizedContent,
            'fields' => $fields->toArray(),
        ]);

        $this->repository->updateOrCreate($record);

        return true;
    }

    public function updateIndex(Searchable $model): void
    {
        $this->index($model, true);
    }

    public function deleteIndex(Searchable $model): void
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $model->getMorphClass(),
            searchable_id: (string) $model->getKey(),
        );

        $this->repository->deleteBulk($filters);
    }

    public function indexAll(StringTypedCollection $modelClasses, bool $force = false, ?callable $callback = null): IndexStatsVO
    {
        $stats = new IndexStatsVO;
        $batchSize = $this->config->getBatchSize();

        foreach ($modelClasses as $modelClass) {
            if (! class_exists($modelClass)) {
                throw new \InvalidArgumentException("Model class not found: {$modelClass}");
            }

            $implements = class_implements($modelClass);
            if (! in_array(Searchable::class, $implements, true)) {
                throw new \InvalidArgumentException(
                    "Model {$modelClass} must implement ".Searchable::class
                );
            }

            $modelInstance = new $modelClass;
            $query = $modelInstance->newQuery();

            $query->chunk($batchSize, function ($models) use ($force, $callback, &$stats) {
                foreach ($models as $model) {
                    try {
                        if (! $model->shouldBeIndexed()) {
                            continue;
                        }

                        $isNew = $this->index($model, $force);

                        if ($isNew) {
                            $stats = $stats->incrementIndexed();
                        } else {
                            $stats = $stats->incrementSkipped();
                        }

                        if ($callback) {
                            $callback($model, $isNew);
                        }
                    } catch (\Exception $e) {
                        $stats = $stats->incrementErrors();

                        if ($callback) {
                            $callback($model, false, $e);
                        }
                    }
                }
            });
        }

        return $stats;
    }

    public function clearIndex(): void
    {
        // Utiliser la méthode deleteAll() du repository
        $this->repository->deleteAll();
    }
}
