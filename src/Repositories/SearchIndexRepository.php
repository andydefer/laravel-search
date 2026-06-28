<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Repositories\SearchIndexRepositoryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\WordVectorParserInterface;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SearchIndexRepository extends AbstractRepository implements SearchIndexRepositoryInterface
{
    public function __construct(
        private readonly NgramInterface $ngramService,
        private readonly WordVectorParserInterface $wordVectorParser,
        private readonly SearchConfig $config,
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
        $uris = $this->wordVectorParser->parse([$word->getValue()]);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => $uris,
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
        $uris = $this->wordVectorParser->parse([$word->getValue()]);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => $uris,
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
        $uris = $this->wordVectorParser->parse([$word->getValue()]);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $sourceType,
            'searchable_id' => $sourceId,
            'item_words' => $uris,
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
        $uris = $this->wordVectorParser->parse([$word->getValue()]);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => $uris,
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
    // MÉTHODE POUR RÉCUPÉRER DES CANDIDATS PAR SIMILARITÉ
    // ============================================================

    public function findCandidatesBySimilarity(
        SearchCandidatesVO $candidates,
        WordVectorCollection $queryWordVectors,
        int $minCommonBigrams = 2
    ): SearchIndexCollection {
        $filters = $candidates->getFilters();

        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);

        // Extraire tous les bigrams de la requête
        $queryBigrams = [];

        foreach ($queryWordVectors as $vector) {
            $queryBigrams = array_merge($queryBigrams, $vector->bigrams->toArray());
        }

        $queryBigrams = array_unique($queryBigrams);

        // Rechercher les indexes qui ont des bigrams communs
        $query->where(function ($q) use ($queryBigrams) {
            foreach ($queryBigrams as $bigram) {
                $q->orWhere('item_words', 'like', '%'.$bigram.'%');
            }
        });

        $query->limit($this->config->getMaxCandidates());
        $query->orderBy('created_at', 'desc');

        $models = $query->get();

        $collection = new SearchIndexCollection;
        foreach ($models as $model) {
            $collection->add($model->toRecord());
        }

        return $collection;
    }
}
