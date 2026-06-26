<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\ValueObjects\ItemWordsVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;
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
        public readonly ?ItemWordsVO $item_words = null,
        public readonly ?NgramsVO $ngrams = null,
    ) {}
}
