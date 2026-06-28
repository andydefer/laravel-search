<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SearchIndexRepository extends AbstractRepository
{
    public function __construct(
        private readonly NgramService $ngramService,
        private readonly WordVectorParserService $wordVectorParser,
    ) {
        parent::__construct(SearchIndex::class, SearchIndexRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof SearchIndexFiltersRecord) {
            return;
        }

        if ($filters->id !== null) {
            $query->where('id', $filters->id->getValue());
        }

        if ($filters->searchable_type !== null) {
            $query->where('searchable_type', $filters->searchable_type->getValue());
        }

        if ($filters->searchable_id !== null) {
            $query->where('searchable_id', $filters->searchable_id->getValue());
        }

        if ($filters->source_column !== null) {
            $query->where('source_column', $filters->source_column->getValue());
        }

        if ($filters->original_text !== null) {
            $query->where('original_text', 'like', '%'.$filters->original_text->getValue().'%');
        }

        if ($filters->normalized_text !== null) {
            $query->where('normalized_text', 'like', '%'.$filters->normalized_text->getValue().'%');
        }

        if ($filters->item_words !== null) {
            foreach ($filters->item_words->toArray() as $wordUri) {
                $query->whereJsonContains('item_words', $wordUri);
            }
        }

        if ($filters->ngrams !== null) {
            foreach ($filters->ngrams->toArray() as $ngram) {
                $query->whereJsonContains('ngrams', $ngram);
            }
        }
    }

    // ============================================================
    // MÉTHODES DE RECHERCHE DE BASE
    // ============================================================

    public function findByWord(StringVO $word): Collection
    {
        $collection = $this->wordVectorParser->parse([$word->getValue()]);
        $uris = $this->wordVectorParser->unparse($collection);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => StringTypedCollection::from($uris),
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findByWordWithSort(
        StringVO $word,
        SortColumns $sort,
        int $limit = 10,
        ?SelectColumns $columns = null
    ): Collection {
        $collection = $this->wordVectorParser->parse([$word->getValue()]);
        $uris = $this->wordVectorParser->unparse($collection);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => StringTypedCollection::from($uris),
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
            'limit' => $limit,
            'sortBy' => $sort,
            'columns' => $columns ?? SelectColumns::from(['id', 'original_text', 'source_column', 'created_at']),
        ]);

        return $this->findBy($findBy);
    }

    public function findByNgram(StringVO $ngram): Collection
    {
        $ngrams = StringTypedCollection::from($this->ngramService->generate($ngram->getValue())->toArray());

        $filters = SearchIndexFiltersRecord::from([
            'ngrams' => $ngrams,
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findByNgramWithSort(
        StringVO $ngram,
        SortColumns $sort,
        int $limit = 10
    ): Collection {
        $ngrams = StringTypedCollection::from($this->ngramService->generate($ngram->getValue())->toArray());

        $filters = SearchIndexFiltersRecord::from([
            'ngrams' => $ngrams,
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
            'limit' => $limit,
            'sortBy' => $sort,
            'columns' => SelectColumns::all(),
        ]);

        return $this->findBy($findBy);
    }

    public function findByWordForNgrams(StringVO $word): Collection
    {
        $ngrams = StringTypedCollection::from($this->ngramService->generate($word->getValue())->toArray());

        $filters = SearchIndexFiltersRecord::from([
            'ngrams' => $ngrams,
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findBySource(StringVO $sourceType, ?StringVO $sourceId = null): Collection
    {
        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $sourceType,
        ]);

        if ($sourceId !== null) {
            $filters = SearchIndexFiltersRecord::from([
                'searchable_type' => $sourceType,
                'searchable_id' => $sourceId,
            ]);
        }

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findBySourceWithSort(
        StringVO $sourceType,
        SortColumns $sort,
        ?StringVO $sourceId = null,
        int $limit = 10
    ): Collection {
        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $sourceType,
        ]);

        if ($sourceId !== null) {
            $filters = SearchIndexFiltersRecord::from([
                'searchable_type' => $sourceType,
                'searchable_id' => $sourceId,
            ]);
        }

        $findBy = FindByRecord::from([
            'filters' => $filters,
            'limit' => $limit,
            'sortBy' => $sort,
            'columns' => SelectColumns::from(['id', 'searchable_type', 'searchable_id', 'original_text', 'created_at']),
        ]);

        return $this->findBy($findBy);
    }

    public function findByText(StringVO $text): Collection
    {
        $filters = SearchIndexFiltersRecord::from([
            'original_text' => $text,
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findByWordAndSource(
        StringVO $word,
        StringVO $sourceType,
        ?StringVO $sourceId = null
    ): Collection {
        $collection = $this->wordVectorParser->parse([$word->getValue()]);
        $uris = $this->wordVectorParser->unparse($collection);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $sourceType,
            'searchable_id' => $sourceId,
            'item_words' => StringTypedCollection::from($uris),
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
        ]);

        return $this->findBy($findBy);
    }

    public function findByWithMultipleSort(
        StringVO $word,
        SortColumns $sort,
        int $limit = 20,
        ?SelectColumns $columns = null
    ): Collection {
        $collection = $this->wordVectorParser->parse([$word->getValue()]);
        $uris = $this->wordVectorParser->unparse($collection);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => StringTypedCollection::from($uris),
        ]);

        $findBy = FindByRecord::from([
            'filters' => $filters,
            'limit' => $limit,
            'sortBy' => $sort,
            'columns' => $columns ?? SelectColumns::from(['id', 'original_text', 'source_column', 'created_at']),
        ]);

        return $this->findBy($findBy);
    }

    public function findAllWithSort(SortColumns $sort, int $limit = 100): Collection
    {
        $filters = new SearchIndexFiltersRecord;

        $findBy = FindByRecord::from([
            'filters' => $filters,
            'limit' => $limit,
            'sortBy' => $sort,
            'columns' => SelectColumns::all(),
        ]);

        return $this->findBy($findBy);
    }

    public function countByFilters(SearchIndexFiltersRecord $filters): int
    {
        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    public function countDistinctEntities(string $morphClass): int
    {
        return $this->model->newQuery()
            ->where('searchable_type', $morphClass)
            ->distinct('searchable_id')
            ->count('searchable_id');
    }

    // ============================================================
    // MÉTHODES DE SUPPRESSION
    // ============================================================

    public function deleteBulk(AbstractRecord $filters): int
    {
        return parent::deleteBulk($filters);
    }

    // ============================================================
    // MÉTHODES POUR RÉCUPÉRER DES CANDIDATS
    // ============================================================

    public function findCandidates(SearchCandidatesVO $candidates): Collection
    {
        $words = $candidates->getWords();
        $ngrams = $candidates->getNgrams();
        $filters = $candidates->getFilters();
        $limit = $candidates->getLimit();

        $wordArray = $words->toArray();
        $ngramArray = $ngrams->toArray();

        $hasWords = ! empty($wordArray);
        $hasNgrams = ! empty($ngramArray);

        if (! $hasWords && ! $hasNgrams) {
            return new Collection;
        }

        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);

        $query->where(function ($q) use ($wordArray, $ngramArray, $hasWords, $hasNgrams) {
            if ($hasNgrams) {
                $q->where(function ($subQ) use ($ngramArray) {
                    foreach ($ngramArray as $ngram) {
                        if (is_string($ngram) && strlen(trim($ngram)) >= 2) {
                            $subQ->orWhereJsonContains('ngrams', $ngram);
                        }
                    }
                });
            }

            if ($hasWords) {
                $q->where(function ($subQ) use ($wordArray) {
                    foreach ($wordArray as $word) {
                        if (is_string($word) && strlen(trim($word)) >= 2) {
                            $subQ->orWhere('item_words', 'like', '%'.$word.'%');
                        }
                    }
                });
            }
        });

        $query->limit($limit);
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function findCandidatesBySimilarity(
        SearchCandidatesVO $candidates,
        WordVectorCollection $queryWordVectors,
        int $minCommonBigrams = 2,
        int $minCommonMetaphoneBigrams = 1
    ): Collection {
        $words = $candidates->getWords();
        $ngrams = $candidates->getNgrams();
        $filters = $candidates->getFilters();
        $limit = $candidates->getLimit();

        $wordArray = $words->toArray();
        $ngramArray = $ngrams->toArray();

        $hasWords = ! empty($wordArray);
        $hasNgrams = ! empty($ngramArray);

        if (! $hasWords && ! $hasNgrams) {
            return new Collection;
        }

        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);

        // Extraire les bigrams et metaphone_bigrams de la requête
        $queryBigrams = $queryWordVectors->getAllBigrams();
        $queryMetaphoneBigrams = $queryWordVectors->getAllMetaphoneBigrams();

        $query->where(function ($q) use ($wordArray, $ngramArray, $hasWords, $hasNgrams) {
            // Recherche par n-grams (OR)
            if ($hasNgrams) {
                $q->where(function ($subQ) use ($ngramArray) {
                    foreach ($ngramArray as $ngram) {
                        if (is_string($ngram) && strlen(trim($ngram)) >= 2) {
                            $subQ->orWhereJsonContains('ngrams', $ngram);
                        }
                    }
                });
            }

            // Recherche par similarité sur item_words (bigrams et metaphone)
            if ($hasWords) {
                $q->orWhere(function ($subQ) use ($wordArray) {
                    foreach ($wordArray as $word) {
                        if (is_string($word) && strlen(trim($word)) >= 2) {
                            // Au moins $minCommonBigrams bigrams communs
                            $subQ->orWhere('item_words', 'like', '%'.$word.'%');
                        }
                    }
                });
            }
        });

        $query->limit($limit);
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }
}
