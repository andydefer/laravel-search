<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;

final class SearchableFieldCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchableFieldRecord::class);
    }
}
