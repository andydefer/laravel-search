<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;

final class ProcessedWordRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly NgramsVO $ngrams,
        public readonly ?float $maxScore = null,
    ) {}
}