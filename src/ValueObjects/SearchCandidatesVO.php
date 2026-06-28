<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;

final class SearchCandidatesVO extends AbstractValueObject
{
    private Sequential $words;

    private Sequential $ngrams;

    private SearchIndexFiltersRecord $filters;

    private int $limit;

    public function __construct(
        Sequential $words,
        Sequential $ngrams,
        SearchIndexFiltersRecord $filters,
        int $limit = 100
    ) {
        $this->words = $words;
        $this->ngrams = $ngrams;
        $this->filters = $filters;
        $this->limit = $limit;
    }

    public static function empty(int $limit = 100): self
    {
        return new self(
            words: Sequential::from([]),
            ngrams: Sequential::from([]),
            filters: new SearchIndexFiltersRecord,
            limit: $limit,
        );
    }

    public function getWords(): Sequential
    {
        return $this->words;
    }

    public function getNgrams(): Sequential
    {
        return $this->ngrams;
    }

    public function getFilters(): SearchIndexFiltersRecord
    {
        return $this->filters;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function hasWords(): bool
    {
        return ! $this->words->isEmpty();
    }

    public function hasNgrams(): bool
    {
        return ! $this->ngrams->isEmpty();
    }

    public function toArray(): array
    {
        return [
            'words' => $this->words->toArray(),
            'ngrams' => $this->ngrams->toArray(),
            'filters' => $this->filters->toArray(),
            'limit' => $this->limit,
        ];
    }

    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from($this->toArray());
    }
}
