<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\WordVectorRecord;

final class WordVectorCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(WordVectorRecord::class);
    }

    public function getWords(): array
    {
        return array_map(fn (WordVectorRecord $record) => $record->word, $this->items);
    }

    public function getMetaphones(): array
    {
        return array_map(fn (WordVectorRecord $record) => $record->metaphone, $this->items);
    }

    public function getAllBigrams(): array
    {
        $bigrams = [];
        foreach ($this->items as $record) {
            $bigrams = array_merge($bigrams, $record->bigrams->toArray());
        }

        return array_unique($bigrams);
    }

    public function getAllMetaphoneBigrams(): array
    {
        $bigrams = [];
        foreach ($this->items as $record) {
            $bigrams = array_merge($bigrams, $record->metaphoneBigrams->toArray());
        }

        return array_unique($bigrams);
    }
}
