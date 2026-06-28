<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class ItemWordRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringVO $normalized,
        public readonly StringTypedCollection $ngrams,
        public readonly FloatVO $max_score,
        public readonly ?SearchIndexRecord $search_index = null,
    ) {}
}
