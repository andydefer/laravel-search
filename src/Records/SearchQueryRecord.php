<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $query,
        public readonly int $limit,
        public readonly ?string $type = null,
    ) {}
}
