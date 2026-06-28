<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SyncResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $indexed,
        public readonly int $deleted,
        public readonly int $skipped,
        public readonly int $total,
    ) {}
}
