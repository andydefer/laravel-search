<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SearchMatchRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $item,
        public readonly float $score,
        public readonly float $max_possible,
        public readonly float $percentage,
    ) {}
}
