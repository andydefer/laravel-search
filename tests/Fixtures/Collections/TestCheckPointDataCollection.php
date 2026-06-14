<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Tests\Fixtures\Data\TestCheckPointData;

final class TestCheckPointDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestCheckPointData::class);
    }

    public function getActive(): self
    {
        return $this->filter(fn (TestCheckPointData $checkpoint) => $checkpoint->is_active);
    }
}
