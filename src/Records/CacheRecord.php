<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class CacheRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled,
        public readonly int $ttl,
        public readonly string $prefix,
    ) {}
}
