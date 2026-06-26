<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class MatchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly float $score,
        public readonly float $max_possible,
        public readonly float $percentage,
    ) {}
}
