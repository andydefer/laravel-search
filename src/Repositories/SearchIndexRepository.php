<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\ValueObjects\ItemWordsVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SearchIndexRepository extends AbstractRepository
{
    private QueryProcessorService $query_processor;

    public function __construct()
    {
        parent::__construct(SearchIndex::class, SearchIndexRecord::class);
        $this->query_processor = new QueryProcessorService(new TextNormalizerService);
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

        if ($filters->item_words !== null) {
            foreach ($filters->item_words->toArray() as $word) {
                $query->whereJsonContains('item_words', $word);
            }
        }

        if ($filters->ngrams !== null) {
            foreach ($filters->ngrams->toArray() as $ngram) {
                $query->whereJsonContains('ngrams', $ngram);
            }
        }
    }

    public function generate_uuid(): string
    {
        return (string) Str::uuid();
    }

    // ========== RECHERCHES ==========

    public function findByWord(StringVO $word): Collection
    {
        $item_words = new ItemWordsVO($word->getValue());

        $filters = new SearchIndexFiltersRecord(
            item_words: $item_words
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findByWordWithSort(
        StringVO $word,
        string $sort = 'created_at:desc',
        int $limit = 10,
        array $columns = ['id', 'original_text', 'source_column', 'created_at']
    ): Collection {
        $item_words = new ItemWordsVO($word->getValue());

        $filters = new SearchIndexFiltersRecord(
            item_words: $item_words
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: new SortColumns($sort),
            columns: new SelectColumns($columns)
        );

        return $this->findBy($findBy);
    }

    public function findByNgram(StringVO $ngram): Collection
    {
        $ngrams_vo = new NgramsVO($ngram->getValue());

        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngrams_vo
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findByNgramWithSort(
        StringVO $ngram,
        string $sort = 'created_at:desc|original_text:asc',
        int $limit = 10
    ): Collection {
        $ngrams_vo = new NgramsVO($ngram->getValue());

        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngrams_vo
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: new SortColumns($sort),
            columns: SelectColumns::all()
        );

        return $this->findBy($findBy);
    }

    public function findByNgramsVO(NgramsVO $ngrams): Collection
    {
        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngrams
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    /**
     * Recherche les indexes correspondant à une liste de n-grams
     * Compatible SQLite, MySQL, PostgreSQL
     */
    public function findByNgramsList(array|NgramsVO $ngrams): Collection
    {
        if ($ngrams instanceof NgramsVO) {
            $ngrams = $ngrams->toArray();
        }

        if (empty($ngrams)) {
            return new Collection;
        }

        $ngrams = array_unique(array_filter($ngrams, function ($ngram) {
            return is_string($ngram) && strlen(trim($ngram)) >= 2;
        }));

        if (empty($ngrams)) {
            return new Collection;
        }

        $query = $this->model->newQuery();

        $query->where(function ($q) use ($ngrams) {
            foreach ($ngrams as $ngram) {
                $q->orWhereJsonContains('ngrams', $ngram);
            }
        });

        $query->limit(1000);
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * Recherche par liste de n-grams avec tri et limite
     */
    public function findByNgramsListWithSort(
        array|NgramsVO $ngrams,
        string $sort = 'created_at:desc',
        int $limit = 50
    ): Collection {
        if ($ngrams instanceof NgramsVO) {
            $ngrams = $ngrams->toArray();
        }

        if (empty($ngrams)) {
            return new Collection;
        }

        $ngrams = array_unique(array_filter($ngrams, function ($ngram) {
            return is_string($ngram) && strlen(trim($ngram)) >= 2;
        }));

        if (empty($ngrams)) {
            return new Collection;
        }

        $query = $this->model->newQuery();

        $query->where(function ($q) use ($ngrams) {
            foreach ($ngrams as $ngram) {
                $q->orWhereJsonContains('ngrams', $ngram);
            }
        });

        $this->applySort($query, $sort);
        $query->limit($limit);

        return $query->get();
    }

    public function findByWordForNgrams(StringVO $word): Collection
    {
        $ngrams_vo = new NgramsVO($word->getValue());

        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngrams_vo
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findBySource(StringVO $sourceType, ?StringVO $sourceId = null): Collection
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $sourceType
        );

        if ($sourceId !== null) {
            $filters = new SearchIndexFiltersRecord(
                searchable_type: $sourceType,
                searchable_id: $sourceId
            );
        }

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findBySourceWithSort(
        StringVO $sourceType,
        ?StringVO $sourceId = null,
        string $sort = 'created_at:desc',
        int $limit = 10
    ): Collection {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $sourceType
        );

        if ($sourceId !== null) {
            $filters = new SearchIndexFiltersRecord(
                searchable_type: $sourceType,
                searchable_id: $sourceId
            );
        }

        $findBy = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: new SortColumns($sort),
            columns: new SelectColumns(['id', 'searchable_type', 'searchable_id', 'original_text', 'created_at'])
        );

        return $this->findBy($findBy);
    }

    public function findByText(StringVO $text): Collection
    {
        $filters = new SearchIndexFiltersRecord(
            original_text: $text
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findByWordAndSource(
        StringVO $word,
        StringVO $sourceType,
        ?StringVO $sourceId = null
    ): Collection {
        $item_words = new ItemWordsVO($word->getValue());

        $filters = new SearchIndexFiltersRecord(
            searchable_type: $sourceType,
            searchable_id: $sourceId,
            item_words: $item_words
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        return $this->findBy($findBy);
    }

    public function findByWithMultipleSort(
        StringVO $word,
        string $sort = 'source_column:asc|created_at:desc',
        int $limit = 20,
        array $columns = ['id', 'original_text', 'source_column', 'created_at']
    ): Collection {
        $item_words = new ItemWordsVO($word->getValue());

        $filters = new SearchIndexFiltersRecord(
            item_words: $item_words
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: new SortColumns($sort),
            columns: new SelectColumns($columns)
        );

        return $this->findBy($findBy);
    }

    public function findAllWithSort(string $sort = 'created_at:desc', int $limit = 100): Collection
    {
        $filters = new SearchIndexFiltersRecord;

        $findBy = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: new SortColumns($sort),
            columns: SelectColumns::all()
        );

        return $this->findBy($findBy);
    }

    // ========== RECHERCHE AVEC SCORE ==========

    /**
     * Recherche avec calcul de score - Version optimisée
     */
    public function searchWithScore(string $query, int $limit = 10): array
    {
        $query_words = $this->query_processor->process($query);

        if (empty($query_words)) {
            return [];
        }

        $query_ngrams = [];
        foreach ($query_words as $word) {
            $query_ngrams = array_merge($query_ngrams, $word->ngrams->toArray());
        }
        $query_ngrams = array_unique($query_ngrams);

        $candidates = $this->findByNgramsList($query_ngrams);

        if ($candidates->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($candidates as $index) {
            $item_words = $index->getItemWords()->toArray();
            $score = $this->query_processor->compute_score($query_words, $item_words);

            if ($score !== null && $score->percentage > 0) {
                $results[] = new SearchResultRecord(
                    index: $index->toRecord(),
                    score: $score->score,
                    max_possible: $score->max_possible,
                    percentage: $score->percentage,
                );
            }
        }

        return $this->query_processor->sort_results($results);
    }

    public function searchWithScoreAndLimit(string $query, int $limit = 10, float $min_percentage = 20): array
    {
        $results = $this->searchWithScore($query);

        $filtered = array_filter($results, function (SearchResultRecord $result) use ($min_percentage) {
            return $result->percentage >= $min_percentage;
        });

        return array_slice($filtered, 0, $limit);
    }

    // ========== MÉTHODES UTILITAIRES ==========

    private function applySort(Builder $query, string $sort): void
    {
        if (empty($sort)) {
            return;
        }

        $sortParts = explode('|', $sort);
        foreach ($sortParts as $part) {
            [$column, $direction] = explode(':', $part) + [1 => 'asc'];
            $query->orderBy($column, $direction);
        }
    }
}
