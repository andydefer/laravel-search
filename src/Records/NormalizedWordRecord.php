<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\ValueObjects\NormalizedWordVO;

final class NormalizedWordRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $original,
        public readonly NormalizedWordVO $normalized,
    ) {}
}
