<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\SearchableModelRecord;

final class SearchableModelCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchableModelRecord::class);
    }

    public function getClassNames(): array
    {
        return array_map(fn (SearchableModelRecord $record) => $record->class->getValue(), $this->items);
    }

    public function getPaths(): array
    {
        return array_map(fn (SearchableModelRecord $record) => $record->path->getValue(), $this->items);
    }

    public function getMorphClasses(): array
    {
        return array_map(fn (SearchableModelRecord $record) => $record->morph_class->getValue(), $this->items);
    }

    public function getTables(): array
    {
        return array_map(
            fn (SearchableModelRecord $record) => $record->table?->getValue(),
            $this->items
        );
    }

    public function findByClass(string $className): ?SearchableModelRecord
    {
        foreach ($this->items as $record) {
            if ($record->class->getValue() === $className) {
                return $record;
            }
        }

        return null;
    }

    public function findByMorphClass(string $morphClass): ?SearchableModelRecord
    {
        foreach ($this->items as $record) {
            if ($record->morph_class->getValue() === $morphClass) {
                return $record;
            }
        }

        return null;
    }
}
