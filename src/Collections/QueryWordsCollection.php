<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;

final class QueryWordsCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(QueryWordRecord::class);
    }

    public function getAllNgrams(): Sequential
    {
        $ngrams = [];
        foreach ($this->items as $word) {
            $ngrams = array_merge($ngrams, $word->ngrams->toArray());
        }

        return Sequential::from(array_unique($ngrams));
    }

    public function getNormalizedWords(): Sequential
    {
        return Sequential::from(
            array_map(fn (QueryWordRecord $word) => $word->normalized, $this->items)
        );
    }
}
