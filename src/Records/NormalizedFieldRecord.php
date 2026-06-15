<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpServices\Enums\PrimitiveType;

final class NormalizedFieldRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $original_value,
        public readonly string $normalized_value,
        public readonly PrimitiveType $primitive_type,
    ) {}
}
