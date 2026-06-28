<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;

final class ItemWordsCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ItemWordRecord::class);
    }

    public function getNormalizedWords(): Sequential
    {
        return Sequential::from(
            array_map(fn (ItemWordRecord $word) => $word->normalized, $this->items)
        );
    }
}
