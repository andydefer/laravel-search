<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use InvalidArgumentException;

/**
 * Value Object for search query.
 */
final class SearchQueryVO extends AbstractValueObject
{
    public function __construct(
        public readonly string $query,
        public readonly int $limit = 5,
    ) {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }
    }

    public function getValue(): SearchQueryRecord
    {
        return new SearchQueryRecord(
            query: $this->query,
            limit: $this->limit,
        );
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getCacheKey(): string
    {
        return 'search_'.md5($this->query.'_'.$this->limit);
    }
}
