<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;

final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchIndexRecord $search_index,
        public readonly FloatVO $score,
        public readonly FloatVO $max_possible,
        public readonly FloatVO $percentage,
        public readonly StrictAssociative $data,
    ) {}
}
