<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Collections\NgramCollection;

/**
 * Value Object for normalized word.
 */
final class NormalizedWordVO extends AbstractValueObject
{
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly NgramCollection $ngrams,
        public readonly float $maxScore,
    ) {}

    public function getValue(): string
    {
        return $this->normalized;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function getNormalized(): string
    {
        return $this->normalized;
    }

    public function getNgrams(): NgramCollection
    {
        return $this->ngrams;
    }

    public function getMaxScore(): float
    {
        return $this->maxScore;
    }

    public function getLength(): int
    {
        return strlen($this->normalized);
    }
}
