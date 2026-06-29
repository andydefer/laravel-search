<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SearchableModelRecord extends AbstractRecord
{
    public function __construct(
        public readonly StringVO $class,
        public readonly StringVO $path,
        public readonly StringVO $morph_class,
        public readonly ?StringVO $table = null,
    ) {}
}
