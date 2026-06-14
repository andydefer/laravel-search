<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class TestPostData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
