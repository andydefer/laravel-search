<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\Collections\SearchResultCollection;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SearchResultCollectionRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchResultCollection $results,
        public readonly int $total,
        public readonly FloatVO $max_percentage,
        public readonly FloatVO $avg_percentage,
        public readonly ?StringVO $query = null,
        public readonly ?int $limit = null,
    ) {}
}
