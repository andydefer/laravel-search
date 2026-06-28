<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;

final class SearchIndexCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchIndexRecord::class);
    }
}
