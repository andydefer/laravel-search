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

    public function toModels(): array
    {
        $models = [];
        foreach ($this->items as $result) {
            $models[] = $result->model;
        }

        return $models;
    }
}
