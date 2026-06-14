<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\Collections\NgramCollection;

final class ProcessedQueryWordRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly NgramCollection $ngrams,
    ) {}
}
