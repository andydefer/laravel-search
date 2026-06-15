<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\LaravelSearch\Collections\SearchResultCollection;
use AndyDefer\LaravelSearch\Contexts\SearchContext;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchEngineServiceInterface;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;

final class SearchService
{
    public function __construct(
        private readonly SearchConfigInterface $config,
        private readonly SearchIndexRepository $repository,
        private readonly SearchEngineServiceInterface $engine,
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function search(SearchQueryRecord $query): SearchResultCollection
    {
        $cacheConfig = $this->config->getCache();
        $cacheKey = $this->getCacheKey($query);

        if ($cacheConfig->enabled && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // CORRECTION: Si c'est déjà un tableau de résultats hydratés
                if (is_array($cached) && ! empty($cached)) {
                    return $this->hydrateResultsFromArray($cached);
                }
                // Si c'est déjà un SearchResultCollection
                if ($cached instanceof SearchResultCollection) {
                    return $cached;
                }
            }
        }

        $results = $this->performSearch($query);

        if ($cacheConfig->enabled) {
            // CORRECTION: Stocker les résultats sous forme sérialisable
            $this->cache->set($cacheKey, $results->toArray(), $cacheConfig->ttl);
        }

        return $results;
    }

    private function performSearch(SearchQueryRecord $query): SearchResultCollection
    {
        $filters = new SearchIndexFiltersRecord;

        if ($query->type !== null) {
            $filters = new SearchIndexFiltersRecord(searchable_type: $query->type);
        }

        $findBy = new FindByRecord(
            filters: $filters,
            limit: null,
            sortBy: null,
            columns: new SelectColumns(['*']),
        );

        $indexes = $this->repository->findBy($findBy);

        if ($indexes->isEmpty()) {
            return new SearchResultCollection;
        }

        $data = [];
        foreach ($indexes as $index) {
            $data[] = $index->content;
        }

        $searchQueryVO = new SearchQueryVO($query->query, $query->limit);
        $searchContext = new SearchContext($searchQueryVO);

        $this->engine->setData($searchContext, $data);
        $this->engine->preprocessData($searchContext);
        $this->engine->search($searchContext);

        return $this->hydrateResults($searchContext, $indexes);
    }

    private function hydrateResults(SearchContext $context, $indexes): SearchResultCollection
    {
        $collection = new SearchResultCollection;

        foreach ($context->getResults() as $result) {
            $matchingIndex = null;
            foreach ($indexes as $index) {
                if ($index->content === $result->item) {
                    $matchingIndex = $index;
                    break;
                }
            }

            if ($matchingIndex && class_exists($matchingIndex->searchable_type)) {
                $model = $matchingIndex->searchable_type::find($matchingIndex->searchable_id);
                if ($model) {
                    $collection->add(new SearchResultRecord(
                        item: $result->item,
                        score: $result->score,
                        max_possible: $result->max_possible,
                        percentage: $result->percentage,
                    ));
                }
            }
        }

        return $collection;
    }

    /**
     * Hydrate les résultats à partir d'un tableau provenant du cache
     */
    private function hydrateResultsFromArray(array $cachedResults): SearchResultCollection
    {
        $collection = new SearchResultCollection;

        foreach ($cachedResults as $result) {
            $collection->add(new SearchResultRecord(
                item: $result['item'] ?? $result['name'] ?? '',
                score: $result['score'] ?? 0,
                max_possible: $result['max_possible'] ?? 0,
                percentage: $result['percentage'] ?? 0,
            ));
        }

        return $collection;
    }

    private function getCacheKey(SearchQueryRecord $query): string
    {
        $cacheConfig = $this->config->getCache();
        $typeKey = $query->type ?? 'all';

        return $cacheConfig->prefix.md5($query->query.'_'.$query->limit.'_'.$typeKey);
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }
}
