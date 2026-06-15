<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $searchable_type,
        public readonly string $searchable_id,  // ← TOUJOURS string !
        public readonly string $item,
        public readonly float $score,
        public readonly float $max_possible,
        public readonly float $percentage,
    ) {}
}
