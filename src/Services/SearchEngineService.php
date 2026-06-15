<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\LaravelSearch\Collections\NormalizedDocumentCollection;
use AndyDefer\LaravelSearch\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelSearch\Contexts\SearchContext;
use AndyDefer\LaravelSearch\Contexts\SearchEngineConfigContext;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchEngineServiceInterface;
use AndyDefer\LaravelSearch\Records\NormalizedDocumentRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\ValueObjects\SearchQueryVO;

final class SearchEngineService implements SearchEngineServiceInterface
{
    public function __construct(
        private readonly SearchEngineConfigContext $engine_config_context,
        private readonly SearchConfigInterface $config,
        private readonly JsonlCacheInterface $cache,
        private readonly NormalizerService $normalizer,
    ) {}

    public function setDocuments(SearchContext $context, NormalizedDocumentCollection $documents): void
    {
        $context->setDocuments($documents);
    }

    public function search(SearchContext $context): SearchContext
    {
        $query = $context->getQuery();
        $cache_key = $this->getCacheKey($query);

        if ($this->cache->has($cache_key)) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                $this->hydrateContextFromCache($context, $cached);

                return $context;
            }
        }

        $results = $this->computeMatches($context, $query);
        $context->setResults($results);

        $this->cache->set($cache_key, $this->serializeResults($results), $this->config->getCacheTtl());

        return $context;
    }

    private function computeMatches(SearchContext $context, SearchQueryVO $query): SearchResultRecordCollection
    {
        $results = new SearchResultRecordCollection;
        $normalized_query = $this->normalizer->normalizeString($query->getQuery());

        foreach ($context->getDocuments() as $document) {
            $best_score = $this->calculateBestMatch($document, $normalized_query);

            if ($best_score > 0) {
                $results->add(new SearchResultRecord(
                    searchable_type: $document->searchable_type,
                    searchable_id: $document->searchable_id,
                    item: $this->buildDisplayString($document),
                    score: $best_score,
                    max_possible: 100.0,
                    percentage: $best_score,
                ));
            }
        }

        $results->sortByScore();

        return $results->slice(0, $query->getLimit());
    }

    private function calculateBestMatch(NormalizedDocumentRecord $document, string $normalized_query): float
    {
        $best_score = 0.0;

        foreach ($document->fields as $field) {
            $score = $this->calculateFieldMatch($field->normalized_value, $normalized_query);

            if ($score > $best_score) {
                $best_score = min($score, 100.0);
            }
        }

        return $best_score;
    }

    private function calculateFieldMatch(string $field_value, string $normalized_query): float
    {
        if ($field_value === $normalized_query) {
            return 100.0;
        }

        if (str_contains($field_value, $normalized_query)) {
            return 80.0;
        }

        $field_words = explode(' ', $field_value);
        $query_words = explode(' ', $normalized_query);

        $match_count = 0;
        foreach ($query_words as $query_word) {
            foreach ($field_words as $field_word) {
                if ($field_word === $query_word || str_contains($field_word, $query_word)) {
                    $match_count++;
                    break;
                }
            }
        }

        if ($match_count > 0) {
            return ($match_count / count($query_words)) * 50.0;
        }

        return 0.0;
    }

    private function buildDisplayString(NormalizedDocumentRecord $document): string
    {
        $display_fields = ['title', 'name', 'label', 'description'];

        foreach ($display_fields as $field_name) {
            foreach ($document->fields as $field) {
                if ($field->name === $field_name) {
                    return $field->original_value;
                }
            }
        }

        foreach ($document->fields as $field) {
            return $field->original_value;
        }

        return $document->searchable_type.'#'.$document->searchable_id;
    }

    private function serializeResults(SearchResultRecordCollection $results): array
    {
        $serialized = [];
        foreach ($results as $result) {
            $serialized[] = [
                'searchable_type' => $result->searchable_type,
                'searchable_id' => $result->searchable_id,
                'item' => $result->item,
                'score' => $result->score,
                'max_possible' => $result->max_possible,
                'percentage' => $result->percentage,
            ];
        }

        return $serialized;
    }

    private function getCacheKey(SearchQueryVO $query): string
    {
        return $this->config->getCachePrefix().md5($query->getQuery().'_'.$query->getLimit());
    }

    private function hydrateContextFromCache(SearchContext $context, array $cached_results): void
    {
        $results = new SearchResultRecordCollection;

        foreach ($cached_results as $result) {
            $results->add(new SearchResultRecord(
                searchable_type: $result['searchable_type'],
                searchable_id: $result['searchable_id'],
                item: $result['item'],
                score: $result['score'],
                max_possible: $result['max_possible'],
                percentage: $result['percentage'],
            ));
        }

        $context->setResults($results);
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }
}
