<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpServices\Enums\PrimitiveType;

final class SearchableFieldRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly PrimitiveType $primitive_type = PrimitiveType::STRING,
    ) {}
}
