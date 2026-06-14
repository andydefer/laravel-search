<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class TestPostRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $user_id,
        public readonly string $title,
        public readonly string $body,
        public readonly string $created_at,
    ) {}
}
