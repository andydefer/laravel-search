<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\ValueObjects\NgramVO;

final class NgramCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(NgramVO::class);
    }

    public function getTotalWeight(): float
    {
        $total = 0.0;
        foreach ($this->items as $ngram) {
            $total += $ngram->getWeight();
        }

        return round($total, 1);
    }
}
