<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class QueryWordRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringVO $original,
        public readonly StringVO $normalized,
        public readonly Sequential $ngrams,
    ) {}
}
