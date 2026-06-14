<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Collections\NgramCollection;
use AndyDefer\LaravelSearch\Records\ProcessedQueryWordRecord;

/**
 * Value Object for processed query word.
 */
final class ProcessedQueryWordVO extends AbstractValueObject
{
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly NgramCollection $ngrams,
    ) {}

    public function getValue(): ProcessedQueryWordRecord
    {
        return new ProcessedQueryWordRecord(
            original: $this->original,
            normalized: $this->normalized,
            ngrams: $this->ngrams,
        );
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

    public function getLength(): int
    {
        return strlen($this->normalized);
    }
}
