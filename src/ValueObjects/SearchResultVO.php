<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;

/**
 * Value Object for search result.
 */
final class SearchResultVO extends AbstractValueObject
{
    public function __construct(
        public readonly string $item,
        public readonly float $score,
        public readonly float $maxPossible,
        public readonly float $percentage,
    ) {}

    public function getValue(): SearchResultRecord
    {
        return new SearchResultRecord(
            item: $this->item,
            score: $this->score,
            max_possible: $this->maxPossible,
            percentage: $this->percentage,
        );
    }

    public function getItem(): string
    {
        return $this->item;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function isRelevant(): bool
    {
        return $this->percentage >= 10.0;
    }
}
