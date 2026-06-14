<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class IndexStatsRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $indexed = 0,
        public readonly int $skipped = 0,
        public readonly int $errors = 0,
    ) {}
}
