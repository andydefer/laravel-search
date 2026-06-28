<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SearchIndexFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?UuidVO $id = null,
        public readonly ?StringVO $searchable_type = null,
        public readonly ?StringVO $searchable_id = null,
        public readonly ?StringVO $source_column = null,
        public readonly ?StringVO $original_text = null,
        public readonly ?StringVO $normalized_text = null,
        public readonly ?Sequential $item_words = null,
        public readonly ?Sequential $ngrams = null,
    ) {}
}
