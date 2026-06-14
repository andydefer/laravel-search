<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Format record for TestCheckPoint model.
 * Used for custom formatting when returning search results.
 *
 * PURE DATA CONTAINER - NO LOGIC.
 */
final class TestCheckPointFormatRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $location,
        public readonly bool $is_active,
    ) {}
}
