<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class WordVectorRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $word,
        public readonly string $metaphone,
        public readonly StringTypedCollection $unique_letters,
        public readonly StringTypedCollection $bigrams,
        public readonly StringTypedCollection $metaphone_bigrams,
    ) {}
}
