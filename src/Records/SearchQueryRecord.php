<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\ValueObjects\SortColumns;

final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringVO $query,
        public readonly ?StringVO $searchable_type = null,
        public readonly ?StringVO $searchable_id = null,
        public readonly ?StringVO $source_column = null,
        public readonly ?int $limit = 10,
        public readonly ?FloatVO $min_percentage = new FloatVO(20),
        public readonly ?StringTypedCollection $columns = null,
        public readonly ?SortColumns $sort = new SortColumns('created_at:desc'),
    ) {}
}
