<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

/**
 * Value Object for n-gram.
 */
final class NgramVO extends AbstractValueObject
{
    public function __construct(
        public readonly string $value,
        public readonly int $length,
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getWeight(): float
    {
        return $this->length + (($this->length - 1) * 0.5);
    }
}
