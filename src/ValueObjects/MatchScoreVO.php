<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Records\MatchScoreRecord;

/**
 * Value Object for match score result.
 */
final class MatchScoreVO extends AbstractValueObject
{
    public function __construct(
        public readonly float $score,
        public readonly float $maxPossible,
        public readonly float $percentage,
    ) {}

    public function getValue(): MatchScoreRecord
    {
        return new MatchScoreRecord(
            score: $this->score,
            max_possible: $this->maxPossible,
            percentage: $this->percentage,
        );
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getMaxPossible(): float
    {
        return $this->maxPossible;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function isValid(): bool
    {
        return $this->score > 0 && $this->maxPossible > 0;
    }
}
