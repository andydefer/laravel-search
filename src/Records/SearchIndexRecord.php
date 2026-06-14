<?php

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;

final class SearchIndexRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $searchable_type = null,
        public readonly ?string $searchable_id = null,
        public readonly ?string $content = null,
        public readonly ?string $normalized_content = null,
        public readonly SearchableFieldCollection $fields = new SearchableFieldCollection
    ) {}
}
