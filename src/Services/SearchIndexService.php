<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Contracts\Indexable;
use AndyDefer\LaravelSearch\Contracts\Services\SearchIndexServiceInterface;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class SearchIndexService implements SearchIndexServiceInterface
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly SearchIndexRepository $repository,
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
    ) {}

    public function index(Indexable $entity): SearchIndexCollection
    {
        if (! $entity->shouldBeIndexed()) {
            throw new \RuntimeException('Entity should not be indexed');
        }

        $data = $entity->getIndexableData();
        $texts = $data->toArray();

        $collection = new SearchIndexCollection;

        // Un index PAR COLONNE
        foreach ($texts as $column => $value) {
            if (empty($value)) {
                continue;
            }

            $normalizedText = $this->normalizer->normalize((string) $value);
            $words = explode(' ', $normalizedText);
            $ngrams = $this->generateNgramsFromWords($words);

            $record = new SearchIndexRecord(
                id: UuidVO::generate(),
                searchable_type: StringVO::from($entity->getMorphClass()),
                searchable_id: StringVO::from((string) $entity->getKey()),
                source_column: StringVO::from($column),
                original_text: StringVO::from((string) $value),
                normalized_text: StringVO::from($normalizedText),
                item_words: Sequential::from($words),
                ngrams: Sequential::from($ngrams->toArray()),
            );

            $index = $this->repository->create($record);
            $collection->add($index->toRecord());
        }

        return $collection;
    }

    public function indexAll(string $morphClass, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $count = 0;
        $query = $this->getEntitiesQuery($morphClass);

        $query->chunk($batchSize, function (Collection $entities) use (&$count) {
            foreach ($entities as $entity) {
                /** @var Model&Indexable $entity */
                if ($entity->shouldBeIndexed()) {
                    $this->index($entity);
                    $count++;
                }
            }
        });

        return $count;
    }

    public function indexAllWithCallback(string $morphClass, callable $callback, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $count = 0;
        $query = $this->getEntitiesQuery($morphClass);

        $query->chunk($batchSize, function (Collection $entities) use ($callback, &$count) {
            foreach ($entities as $entity) {
                /** @var Model&Indexable $entity */
                if ($entity->shouldBeIndexed()) {
                    $this->index($entity);
                    $count++;
                    $callback($entity, $count);
                }
            }
        });

        return $count;
    }

    public function reindex(Indexable $entity): SearchIndexCollection
    {
        $this->delete($entity);

        return $this->index($entity);
    }

    public function reindexAll(string $morphClass, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $this->deleteAll($morphClass);

        return $this->indexAll($morphClass, $batchSize);
    }

    public function reindexAllWithCallback(string $morphClass, callable $callback, int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $this->deleteAll($morphClass);

        return $this->indexAllWithCallback($morphClass, $callback, $batchSize);
    }

    public function delete(Indexable $entity): bool
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from($entity->getMorphClass()),
            searchable_id: StringVO::from((string) $entity->getKey()),
        );

        $count = $this->repository->deleteBulk($filters);

        return $count > 0;
    }

    public function deleteAll(string $morphClass): int
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from($morphClass),
        );

        return $this->repository->deleteBulk($filters);
    }

    public function deleteAllWithCallback(string $morphClass, callable $callback): int
    {
        $count = 0;
        $query = $this->getEntitiesQuery($morphClass);

        $query->chunk(self::DEFAULT_BATCH_SIZE, function (Collection $entities) use ($callback, &$count) {
            foreach ($entities as $entity) {
                /** @var Model&Indexable $entity */
                if ($this->delete($entity)) {
                    $count++;
                    $callback($entity, $count);
                }
            }
        });

        return $count;
    }

    public function deleteById(string $id): bool
    {
        $filters = new SearchIndexFiltersRecord(
            id: UuidVO::from($id),
        );

        $count = $this->repository->deleteBulk($filters);

        return $count > 0;
    }

    public function deleteByEntityId(string $morphClass, string $entityId): bool
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from($morphClass),
            searchable_id: StringVO::from($entityId),
        );

        $count = $this->repository->deleteBulk($filters);

        return $count > 0;
    }

    public function getIndexedCount(string $morphClass): int
    {
        return $this->repository->countDistinctEntities($morphClass);
    }

    public function getNotIndexedCount(string $morphClass): int
    {
        $total = $this->getEntitiesQuery($morphClass)->count();
        $indexed = $this->getIndexedCount($morphClass);

        return $total - $indexed;
    }

    public function sync(string $morphClass, int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $indexed = 0;
        $deleted = 0;
        $skipped = 0;

        $query = $this->getEntitiesQuery($morphClass);

        $query->chunk($batchSize, function (Collection $entities) use (&$indexed, &$deleted, &$skipped) {
            foreach ($entities as $entity) {
                if ($entity->shouldBeIndexed()) {
                    // Vérifier si déjà indexé
                    /** @var Indexable $entity */
                    $filters = new SearchIndexFiltersRecord(
                        searchable_type: StringVO::from($entity->getMorphClass()),
                        searchable_id: StringVO::from((string) $entity->getKey()),
                    );

                    $existing = $this->repository->count($filters);

                    if ($existing > 0) {
                        // Mettre à jour (reindex)
                        $this->reindex($entity);
                        $indexed++;
                    } else {
                        $this->index($entity);
                        $indexed++;
                    }
                } else {
                    // Si ne doit pas être indexé, supprimer l'index existant
                    $filters = new SearchIndexFiltersRecord(
                        searchable_type: StringVO::from($entity->getMorphClass()),
                        searchable_id: StringVO::from((string) $entity->getKey()),
                    );

                    $count = $this->repository->deleteBulk($filters);
                    if ($count > 0) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
            }
        });

        return [
            'indexed' => $indexed,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'total' => $indexed + $deleted + $skipped,
        ];
    }

    private function generateNgramsFromWords(array $words): Sequential
    {
        $allGrams = [];

        foreach ($words as $word) {
            $ngrams = $this->ngramService->generate($word)->toArray();
            $allGrams = array_merge($allGrams, $ngrams);
        }

        return Sequential::from(array_unique($allGrams));
    }

    private function getEntitiesQuery(string $morphClass): Builder
    {
        if (! class_exists($morphClass)) {
            throw new \InvalidArgumentException("Class {$morphClass} does not exist");
        }

        if (! is_subclass_of($morphClass, Model::class)) {
            throw new \InvalidArgumentException("{$morphClass} must be an instance of ".Model::class);
        }

        /** @var Model $instance */
        $instance = new $morphClass;
        if (! $instance instanceof Indexable) {
            throw new \InvalidArgumentException("{$morphClass} must implement ".Indexable::class);
        }

        return $instance->newQuery();
    }

    private function getEntitiesByMorphClass(string $morphClass): Collection
    {
        return $this->getEntitiesQuery($morphClass)->get();
    }
}
