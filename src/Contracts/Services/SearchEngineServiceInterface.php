<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Contexts\SearchContext;

/**
 * Interface for search engine service.
 * Stateless service that operates on SearchContext.
 */
interface SearchEngineServiceInterface
{
    /**
     * Sets the dataset to search within.
     *
     * @param  SearchContext  $context  The search context
     * @param  array<int, string>  $data  Array of strings to index
     */
    public function setData(SearchContext $context, array $data): void;

    /**
     * Preprocesses all data items in the context.
     * Generates n-grams and normalizes words for faster search.
     *
     * @param  SearchContext  $context  The search context containing raw data
     */
    public function preprocessData(SearchContext $context): void;

    /**
     * Performs search using the context and returns results.
     *
     * @param  SearchContext  $context  The search context containing query and data
     * @return SearchContext The updated context with search results
     */
    public function search(SearchContext $context): SearchContext;

    /**
     * Clears the search cache.
     */
    public function clearCache(): void;
}
