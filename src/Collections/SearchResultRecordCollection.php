<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;

final class SearchResultRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    public function sortByRelevance(): void
    {
        usort($this->items, function (SearchResultRecord $a, SearchResultRecord $b) {
            if ($b->percentage === $a->percentage) {
                return $b->score <=> $a->score;
            }

            return $b->percentage <=> $a->percentage;
        });
    }

    public function getTop(int $limit): self
    {
        $this->sortByRelevance();
        $collection = new self;
        foreach (array_slice($this->items, 0, $limit) as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    public function toResultArray(): array
    {
        $results = [];
        foreach ($this->items as $record) {
            $results[] = [
                'name' => $record->item,
                'score' => $record->score,
                'max_possible' => $record->max_possible,
                'percentage' => $record->percentage,
            ];
        }

        return $results;
    }
}
