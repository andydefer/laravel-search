<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Tests\Fixtures\Records\TestUserRecord;

final class TestUserRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestUserRecord::class);
    }
}
