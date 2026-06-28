<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Records\SearchResultCollectionRecord;

interface SearchServiceInterface
{
    /**
     * Recherche avec scoring complet
     */
    public function search(SearchQueryRecord $query): SearchResultCollectionRecord;

    /**
     * Recherche avec limite et seuil de pertinence
     */
    public function searchWithLimit(SearchQueryRecord $query, int $limit = 10, float $minPercentage = 20): SearchResultCollectionRecord;

    /**
     * Recherche par mots-clés avec filtres
     */
    public function searchWithFilters(SearchQueryRecord $query, SearchIndexFiltersRecord $filters): SearchResultCollectionRecord;
}
