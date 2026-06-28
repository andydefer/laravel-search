<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\LaravelSearch\Collections\MatchResultCollection;
use AndyDefer\LaravelSearch\Collections\SearchResultCollection;
use AndyDefer\LaravelSearch\Collections\SourceColumnCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Contracts\Services\CandidatesFinderServiceInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchServiceInterface;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Records\SearchResultCollectionRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Database\Eloquent\Model;

final class SearchService implements SearchServiceInterface
{
    public function __construct(
        private readonly SearchIndexRepository $repository,
        private readonly QueryProcessorService $queryProcessor,
        private readonly SearchConfig $config,
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
        private readonly CandidatesFinderServiceInterface $candidatesFinder,
    ) {}

    public function search(SearchQueryRecord $query): SearchResultCollectionRecord
    {
        // 1. Traiter la requête
        $queryWords = $this->queryProcessor->process($query->query);

        if ($queryWords->isEmpty()) {
            return $this->emptyResult($query);
        }

        // 2. Récupérer les candidats depuis la base
        $itemWords = $this->candidatesFinder->findCandidates($query);

        if ($itemWords->isEmpty()) {
            return $this->emptyResult($query);
        }

        // 3. Calculer les scores
        $matchResults = $this->queryProcessor->computeScore($queryWords, $itemWords);

        if ($matchResults === null || $matchResults->isEmpty()) {
            return $this->emptyResult($query);
        }

        // 3.5 AGRÉGER PAR INDEX
        $aggregated = [];
        foreach ($matchResults as $match) {
            if ($match->search_index === null) {
                continue;
            }
            $indexId = $match->search_index->id->getValue();
            if (! isset($aggregated[$indexId])) {
                $aggregated[$indexId] = [
                    'index' => $match->search_index,
                    'scores' => [],
                    'percentages' => [],
                ];
            }
            $aggregated[$indexId]['scores'][] = $match->score->getValue();
            $aggregated[$indexId]['percentages'][] = $match->percentage->getValue();
        }

        $mergedResults = new MatchResultCollection;
        foreach ($aggregated as $indexId => $data) {
            $avgPercentage = array_sum($data['percentages']) / count($data['percentages']);
            $avgScore = array_sum($data['scores']) / count($data['scores']);
            $mergedResults->add(MatchResultRecord::from([
                'search_index' => $data['index'],
                'score' => $avgScore,
                'max_possible' => 1.0,
                'percentage' => $avgPercentage,
            ]));
        }

        // 3.6 DÉDUPÉR PAR ENTITÉ (un résultat par searchable_type + searchable_id)
        $bestPerEntity = [];
        foreach ($mergedResults as $match) {
            if ($match->search_index === null) {
                continue;
            }

            $entityKey = $match->search_index->searchable_type->getValue()
                .'|'
                .$match->search_index->searchable_id->getValue();

            $currentPercentage = $match->percentage->getValue();

            if (! isset($bestPerEntity[$entityKey])
                || $currentPercentage > $bestPerEntity[$entityKey]->percentage->getValue()) {
                $bestPerEntity[$entityKey] = $match;
            }
        }

        $deduplicatedResults = new MatchResultCollection;
        foreach ($bestPerEntity as $match) {
            $deduplicatedResults->add($match);
        }

        // 4. Filtrer par seuil de pertinence
        $minPercentage = $query->min_percentage ?? FloatVO::from(20);
        $filtered = $deduplicatedResults->filterByMinPercentage($minPercentage->getValue());

        if ($filtered->isEmpty()) {
            return $this->emptyResult($query);
        }

        // 5. Trier par pertinence
        $sorted = $this->queryProcessor->sortResults($filtered);

        // 6. Limiter les résultats
        $limit = $query->limit ?? 10;
        $limited = $this->take($sorted, $limit);

        // 7. Construire les résultats avec les données formatées
        $results = $this->buildResults($limited);

        // 8. Calculer les statistiques
        $total = $results->count();
        $maxPercentage = $results->getMaxPercentage();
        $avgPercentage = $results->getAvgPercentage();

        return SearchResultCollectionRecord::from([
            'results' => $results,
            'total' => $total,
            'max_percentage' => $maxPercentage,
            'avg_percentage' => round($avgPercentage, 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchWithLimit(SearchQueryRecord $query, int $limit = 10, float $minPercentage = 20): SearchResultCollectionRecord
    {
        $queryWithLimit = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $query->searchable_type,
            'searchable_id' => $query->searchable_id,
            'source_column' => $query->source_column,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($queryWithLimit);
    }

    public function searchWithFilters(SearchQueryRecord $query, SearchIndexFiltersRecord $filters): SearchResultCollectionRecord
    {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $filters->searchable_type ?? $query->searchable_type,
            'searchable_id' => $filters->searchable_id ?? $query->searchable_id,
            'source_column' => $filters->source_column ?? $query->source_column,
            'limit' => $query->limit,
            'min_percentage' => $query->min_percentage,
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchByType(
        SearchQueryRecord $query,
        StringVO $morphClass,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $morphClass,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchByColumn(
        SearchQueryRecord $query,
        StringVO $sourceColumn,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => $sourceColumn,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchByColumns(
        SearchQueryRecord $query,
        SourceColumnCollection $sourceColumns,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => $sourceColumns->first() ?? null,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchByTypeAndColumn(
        SearchQueryRecord $query,
        StringVO $morphClass,
        StringVO $sourceColumn,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $morphClass,
            'searchable_id' => null,
            'source_column' => $sourceColumn,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchByTypeAndColumns(
        SearchQueryRecord $query,
        StringVO $morphClass,
        SourceColumnCollection $sourceColumns,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $morphClass,
            'searchable_id' => null,
            'source_column' => $sourceColumns->first() ?? null,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    public function searchExcludingType(
        SearchQueryRecord $query,
        StringVO $excludedMorphClass,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit * 2,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        $result = $this->search($filteredQuery);

        $filteredResults = $result->results->filter(
            fn (SearchResultRecord $record) => $record->search_index->searchable_type->getValue() !== $excludedMorphClass->getValue()
        );

        return SearchResultCollectionRecord::from([
            'results' => $filteredResults->take($limit),
            'total' => $filteredResults->count(),
            'max_percentage' => $filteredResults->getMaxPercentage(),
            'avg_percentage' => round($filteredResults->getAvgPercentage(), 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchExcludingColumn(
        SearchQueryRecord $query,
        StringVO $excludedSourceColumn,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit * 2,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        $result = $this->search($filteredQuery);

        $filteredResults = $result->results->filter(
            fn (SearchResultRecord $record) => $record->search_index->source_column->getValue() !== $excludedSourceColumn->getValue()
        );

        return SearchResultCollectionRecord::from([
            'results' => $filteredResults->take($limit),
            'total' => $filteredResults->count(),
            'max_percentage' => $filteredResults->getMaxPercentage(),
            'avg_percentage' => round($filteredResults->getAvgPercentage(), 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchExcludingColumns(
        SearchQueryRecord $query,
        SourceColumnCollection $excludedSourceColumns,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $excludedValues = $excludedSourceColumns->getValues();

        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit * 2,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        $result = $this->search($filteredQuery);

        $filteredResults = $result->results->filter(
            fn (SearchResultRecord $record) => ! in_array($record->search_index->source_column->getValue(), $excludedValues, true)
        );

        return SearchResultCollectionRecord::from([
            'results' => $filteredResults->take($limit),
            'total' => $filteredResults->count(),
            'max_percentage' => $filteredResults->getMaxPercentage(),
            'avg_percentage' => round($filteredResults->getAvgPercentage(), 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchExcludingTypeAndColumn(
        SearchQueryRecord $query,
        StringVO $excludedMorphClass,
        StringVO $excludedSourceColumn,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit * 2,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        $result = $this->search($filteredQuery);

        $filteredResults = $result->results->filter(
            fn (SearchResultRecord $record) => $record->search_index->searchable_type->getValue() !== $excludedMorphClass->getValue() &&
                $record->search_index->source_column->getValue() !== $excludedSourceColumn->getValue()
        );

        return SearchResultCollectionRecord::from([
            'results' => $filteredResults->take($limit),
            'total' => $filteredResults->count(),
            'max_percentage' => $filteredResults->getMaxPercentage(),
            'avg_percentage' => round($filteredResults->getAvgPercentage(), 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchExcludingTypeAndColumns(
        SearchQueryRecord $query,
        StringVO $excludedMorphClass,
        SourceColumnCollection $excludedSourceColumns,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $excludedValues = $excludedSourceColumns->getValues();

        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => null,
            'searchable_id' => null,
            'source_column' => null,
            'limit' => $limit * 2,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        $result = $this->search($filteredQuery);

        $filteredResults = $result->results->filter(
            fn (SearchResultRecord $record) => $record->search_index->searchable_type->getValue() !== $excludedMorphClass->getValue() &&
                ! in_array($record->search_index->source_column->getValue(), $excludedValues, true)
        );

        return SearchResultCollectionRecord::from([
            'results' => $filteredResults->take($limit),
            'total' => $filteredResults->count(),
            'max_percentage' => $filteredResults->getMaxPercentage(),
            'avg_percentage' => round($filteredResults->getAvgPercentage(), 2),
            'query' => $query->query,
            'limit' => $limit,
        ]);
    }

    public function searchAdvanced(
        SearchQueryRecord $query,
        SearchIndexFiltersRecord $filters,
        int $limit = 10,
        float $minPercentage = 20
    ): SearchResultCollectionRecord {
        $filteredQuery = SearchQueryRecord::from([
            'query' => $query->query,
            'searchable_type' => $filters->searchable_type,
            'searchable_id' => $filters->searchable_id,
            'source_column' => $filters->source_column,
            'limit' => $limit,
            'min_percentage' => FloatVO::from($minPercentage),
            'columns' => $query->columns,
            'sort' => $query->sort,
        ]);

        return $this->search($filteredQuery);
    }

    private function emptyResult(SearchQueryRecord $query): SearchResultCollectionRecord
    {
        return SearchResultCollectionRecord::from([
            'results' => new SearchResultCollection,
            'total' => 0,
            'max_percentage' => 0.0,
            'avg_percentage' => 0.0,
            'query' => $query->query,
            'limit' => $query->limit,
        ]);
    }

    private function buildResults(MatchResultCollection $matchResults): SearchResultCollection
    {
        $collection = new SearchResultCollection;

        foreach ($matchResults as $match) {
            $searchIndex = $match->search_index;

            if ($searchIndex === null) {
                continue;
            }

            $model = $this->getModelFromSearchIndex($searchIndex);
            $data = $model->getSearchResultFormat();

            $collection->add(SearchResultRecord::from([
                'search_index' => $searchIndex,
                'score' => $match->score,
                'max_possible' => $match->max_possible,
                'percentage' => $match->percentage,
                'data' => $data,
            ]));
        }

        return $collection;
    }

    private function getModelFromSearchIndex(SearchIndexRecord $searchIndex): Searchable
    {
        $morphClass = $searchIndex->searchable_type?->getValue();
        $id = $searchIndex->searchable_id?->getValue();

        if ($morphClass === null || $id === null) {
            throw new \RuntimeException('Search index missing morph class or ID');
        }

        if (! class_exists($morphClass)) {
            throw new \RuntimeException(sprintf('Class %s does not exist', $morphClass));
        }

        if (! is_subclass_of($morphClass, Model::class)) {
            throw new \RuntimeException(sprintf('Class %s must be a Model', $morphClass));
        }

        /** @var Model $model */
        $model = $morphClass::find($id);

        if ($model === null) {
            throw new \RuntimeException(sprintf('Model %s with ID %s not found', $morphClass, $id));
        }

        if (! $model instanceof Searchable) {
            throw new \RuntimeException(sprintf(
                'Model %s must implement %s to be searchable',
                $morphClass,
                Searchable::class
            ));
        }

        return $model;
    }

    private function take(MatchResultCollection $collection, int $limit): MatchResultCollection
    {
        if ($limit <= 0 || $collection->isEmpty()) {
            return new MatchResultCollection;
        }

        $items = $collection->toArray();
        $limited = array_slice($items, 0, $limit);

        $result = new MatchResultCollection;
        foreach ($limited as $item) {
            $result->add($item);
        }

        return $result;
    }
}
