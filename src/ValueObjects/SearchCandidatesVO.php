<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;

final class SearchCandidatesVO extends AbstractValueObject
{
    private StringTypedCollection $words;

    private StringTypedCollection $ngrams;

    private SearchIndexFiltersRecord $filters;

    public function __construct(
        StringTypedCollection $words,
        StringTypedCollection $ngrams,
        SearchIndexFiltersRecord $filters,
    ) {
        $this->words = $words;
        $this->ngrams = $ngrams;
        $this->filters = $filters;
    }

    public static function empty(): self
    {
        return new self(
            words: StringTypedCollection::from([]),
            ngrams: StringTypedCollection::from([]),
            filters: new SearchIndexFiltersRecord,
        );
    }

    public function getWords(): StringTypedCollection
    {
        return $this->words;
    }

    public function getNgrams(): StringTypedCollection
    {
        return $this->ngrams;
    }

    public function getFilters(): SearchIndexFiltersRecord
    {
        return $this->filters;
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
        ];
    }

    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from($this->toArray());
    }

    public static function getTypeName(): string
    {
        return 'search_candidates';
    }
}
