<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\Tests\Fixtures\Data\TestUserData;

final class TestUserDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestUserData::class);
    }
}
