<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\NormalizedWordRecord;

final class NormalizedWordRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(NormalizedWordRecord::class);
    }

    public function findByOriginal(string $original): ?NormalizedWordRecord
    {
        foreach ($this->items as $item) {
            if ($item->original === $original) {
                return $item;
            }
        }

        return null;
    }

    public function getAllNormalizedWords(): array
    {
        $words = [];
        foreach ($this->items as $item) {
            $words[] = $item->normalized->getNormalized();
        }

        return $words;
    }
}
