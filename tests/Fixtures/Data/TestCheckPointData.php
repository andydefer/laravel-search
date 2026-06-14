<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

final class TestCheckPointData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $location,
        public readonly bool $is_active,
        public readonly ?string $last_ping_at,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        public readonly ?string $deleted_at = null,
        public readonly ?DataObject $metadata = null,
    ) {}
}
