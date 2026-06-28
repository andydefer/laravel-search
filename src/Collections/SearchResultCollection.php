<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;

final class SearchResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    public function getBest(): ?SearchResultRecord
    {
        if (empty($this->items)) {
            return null;
        }

        $sorted = $this->sortByRelevance();

        return $sorted->first();
    }

    public function filterByMinPercentage(float $minPercentage): self
    {
        return $this->filter(fn (SearchResultRecord $result) => $result->percentage->getValue() >= $minPercentage);
    }

    public function sortByRelevance(): self
    {
        return $this->usort(function (SearchResultRecord $a, SearchResultRecord $b) {
            if ($b->percentage->getValue() === $a->percentage->getValue()) {
                return $b->score->getValue() <=> $a->score->getValue();
            }

            return $b->percentage->getValue() <=> $a->percentage->getValue();
        });
    }

    public function getMaxPercentage(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $max = 0.0;
        foreach ($this->items as $item) {
            $percentage = $item->percentage->getValue();
            if ($percentage > $max) {
                $max = $percentage;
            }
        }

        return $max;
    }

    public function getAvgPercentage(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += $item->percentage->getValue();
        }

        return $sum / $this->count();
    }

    public function getTotal(): int
    {
        return $this->count();
    }

    public function getPercentages(): array
    {
        $percentages = [];
        foreach ($this->items as $item) {
            $percentages[] = $item->percentage->getValue();
        }

        return $percentages;
    }

    public function getScores(): array
    {
        $scores = [];
        foreach ($this->items as $item) {
            $scores[] = $item->score->getValue();
        }

        return $scores;
    }

    public function take(int $limit): self
    {
        if ($limit <= 0 || $this->isEmpty()) {
            return new self;
        }

        $items = array_slice($this->items, 0, $limit);

        $result = new self;
        foreach ($items as $item) {
            $result->add($item);
        }

        return $result;
    }
}
