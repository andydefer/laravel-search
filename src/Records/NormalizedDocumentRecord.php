<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;

final class NormalizedDocumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $searchable_type,
        public readonly string $searchable_id,
        public readonly SearchableFieldCollection $fields,
    ) {}
}
