<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelSearch\Records\IndexStatsRecord;

final class IndexStatsVO extends AbstractValueObject
{
    private IndexStatsRecord $stats;

    public function __construct(int $indexed = 0, int $skipped = 0, int $errors = 0)
    {
        $this->stats = new IndexStatsRecord(
            indexed: $indexed,
            skipped: $skipped,
            errors: $errors,
        );
    }

    public function incrementIndexed(): self
    {
        return new self(
            indexed: $this->stats->indexed + 1,
            skipped: $this->stats->skipped,
            errors: $this->stats->errors,
        );
    }

    public function incrementSkipped(): self
    {
        return new self(
            indexed: $this->stats->indexed,
            skipped: $this->stats->skipped + 1,
            errors: $this->stats->errors,
        );
    }

    public function incrementErrors(): self
    {
        return new self(
            indexed: $this->stats->indexed,
            skipped: $this->stats->skipped,
            errors: $this->stats->errors + 1,
        );
    }

    public function getValue(): IndexStatsRecord
    {
        return $this->stats;
    }

    protected function getDefaultValue(string $propertyName): mixed
    {
        return match ($propertyName) {
            'stats' => new IndexStatsRecord,
            default => null,
        };
    }
}
