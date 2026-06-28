<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;

final class MatchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly FloatVO $score,
        public readonly FloatVO $max_possible,
        public readonly FloatVO $percentage,
        public readonly ?SearchIndexRecord $search_index = null,
    ) {}
}
