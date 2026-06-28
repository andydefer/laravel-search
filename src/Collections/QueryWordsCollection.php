<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;

final class QueryWordsCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(QueryWordRecord::class);
    }

    public function getAllNgrams(): StringTypedCollection
    {
        $ngrams = [];
        foreach ($this->items as $word) {
            $ngrams = array_merge($ngrams, $word->ngrams->toArray());
        }

        return StringTypedCollection::from(array_unique($ngrams));
    }

    public function getNormalizedWords(): StringTypedCollection
    {
        return StringTypedCollection::from(
            array_map(fn (QueryWordRecord $word) => $word->normalized, $this->items)
        );
    }
}
