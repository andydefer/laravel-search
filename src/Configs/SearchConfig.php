<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Configs;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Records\CacheRecord;
use AndyDefer\LaravelSearch\Records\EngineRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SearchConfig implements SearchConfigInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getEngine(): EngineRecord
    {
        $engine = $this->config->get('fuzzy-search.engine', []);

        return new EngineRecord(
            min_gram_length: (int) ($engine['min_gram_length'] ?? 2),
            max_gram_length: (int) ($engine['max_gram_length'] ?? 4),
            min_letters_match_percentage: (int) ($engine['min_letters_match_percentage'] ?? 30),
            min_length_ratio: (float) ($engine['min_length_ratio'] ?? 0.5),
            max_candidates_per_word: (int) ($engine['max_candidates_per_word'] ?? 5),
            early_stop_threshold: (float) ($engine['early_stop_threshold'] ?? 0.95),
        );
    }

    public function getCache(): CacheRecord
    {
        $cache = $this->config->get('fuzzy-search.cache', []);

        return new CacheRecord(
            enabled: (bool) ($cache['enabled'] ?? true),
            ttl: (int) ($cache['ttl'] ?? 3600),
            prefix: (string) ($cache['prefix'] ?? 'fuzzy_search_'),
        );
    }

    public function getTableName(): string
    {
        return $this->config->get('fuzzy-search.table_name', 'search_index');
    }

    public function getBatchSize(): int
    {
        return (int) $this->config->get('fuzzy-search.batch_size', 100);
    }

    public function isAutoIndexEnabled(): bool
    {
        return (bool) $this->config->get('fuzzy-search.auto_index', true);
    }

    public function getModels(): StringTypedCollection
    {
        $collection = new StringTypedCollection;
        $models = $this->config->get('fuzzy-search.models', []);

        if (! empty($models)) {
            $collection->add(...$models);
        }

        return $collection;
    }

    public function getCacheTtl(): int
    {
        $cache = $this->config->get('fuzzy-search.cache', []);

        return (int) ($cache['ttl'] ?? 3600);
    }

    public function getCachePrefix(): string
    {
        $cache = $this->config->get('fuzzy-search.cache', []);

        return (string) ($cache['prefix'] ?? 'fuzzy_search_');
    }

    public function getRelevanceThreshold(): float
    {
        return (float) $this->config->get('fuzzy-search.relevance_threshold', 10.0);
    }

    public function getMinQueryLength(): int
    {
        return (int) $this->config->get('fuzzy-search.min_query_length', 1);
    }

    public function getMaxWordLengthForHash(): int
    {
        return (int) $this->config->get('fuzzy-search.max_word_length_for_hash', 64);
    }
}
