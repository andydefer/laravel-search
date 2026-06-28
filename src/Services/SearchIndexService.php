<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Contracts\Indexable;
use AndyDefer\LaravelSearch\Contracts\Repositories\SearchIndexRepositoryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchIndexInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Contracts\Services\WordVectorParserInterface;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\WordVectorRecord;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class SearchIndexService implements SearchIndexInterface
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly SearchIndexRepositoryInterface $repository,
        private readonly TextNormalizerInterface $normalizer,
        private readonly NgramInterface $ngramService,
        private readonly WordVectorParserInterface $wordVectorParser,
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

            // Créer les WordVectorRecord pour chaque mot
            $wordVectors = new WordVectorCollection;
            foreach ($words as $word) {
                if (! empty($word)) {
                    $normalized = $this->normalizer->normalize($word);
                    $unique_letters = array_unique(str_split($normalized));
                    $metaphone = metaphone($normalized);

                    $ngrams = $this->ngramService->generate($normalized)->toArray();
                    $bigrams = array_values(array_filter($ngrams, fn ($g) => strlen($g) === 2));

                    $metaphone_bigrams = [];
                    $metaphoneLength = strlen($metaphone);
                    for ($i = 0; $i < $metaphoneLength - 1; $i++) {
                        $metaphone_bigrams[] = substr($metaphone, $i, 2);
                    }

                    $wordVectors->add(WordVectorRecord::from([
                        'word' => $word,
                        'metaphone' => $metaphone,
                        'unique_letters' => $unique_letters,
                        'bigrams' => $bigrams,
                        'metaphone_bigrams' => $metaphone_bigrams,
                    ]));
                }
            }

            // Générer les n-grams
            $ngrams = $this->generateNgramsFromWords($words);

            $uris = $this->wordVectorParser->unparse($wordVectors);

            $record = new SearchIndexRecord(
                id: UuidVO::generate(),
                searchable_type: StringVO::from($entity->getMorphClass()),
                searchable_id: StringVO::from((string) $entity->getKey()),
                source_column: StringVO::from($column),
                original_text: StringVO::from((string) $value),
                normalized_text: StringVO::from($normalizedText),
                item_words: $wordVectors,
                ngrams: StringTypedCollection::from($ngrams->toArray()),
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
                    /** @var Indexable $entity */
                    $filters = new SearchIndexFiltersRecord(
                        searchable_type: StringVO::from($entity->getMorphClass()),
                        searchable_id: StringVO::from((string) $entity->getKey()),
                    );

                    $existing = $this->repository->count($filters);

                    if ($existing > 0) {
                        $this->reindex($entity);
                        $indexed++;
                    } else {
                        $this->index($entity);
                        $indexed++;
                    }
                } else {
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

    private function generateNgramsFromWords(array $words): StringTypedCollection
    {
        $allGrams = [];

        foreach ($words as $word) {
            $ngrams = $this->ngramService->generate($word)->toArray();
            $allGrams = array_merge($allGrams, $ngrams);
        }

        return StringTypedCollection::from(array_unique($allGrams));
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
