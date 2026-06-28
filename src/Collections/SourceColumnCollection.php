<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SourceColumnCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(StringVO::class);
    }

    public function getValues(): array
    {
        return array_map(fn (StringVO $vo) => $vo->getValue(), $this->items);
    }
}
