<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;

final class MatchResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(MatchResultRecord::class);
    }

    public function getBest(): ?MatchResultRecord
    {
        if (empty($this->items)) {
            return null;
        }

        $sorted = $this->sortByRelevance();

        return $sorted->first();
    }

    public function filterByMinPercentage(float $minPercentage): self
    {
        return $this->filter(fn (MatchResultRecord $result) => $result->percentage->getValue() >= $minPercentage);
    }

    public function sortByRelevance(): self
    {
        return $this->usort(function (MatchResultRecord $a, MatchResultRecord $b) {
            if ($b->percentage->getValue() === $a->percentage->getValue()) {
                return $b->score->getValue() <=> $a->score->getValue();
            }

            return $b->percentage->getValue() <=> $a->percentage->getValue();
        });
    }
}
