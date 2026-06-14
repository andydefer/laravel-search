<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class EngineRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $min_gram_length,
        public readonly int $max_gram_length,
        public readonly int $min_letters_match_percentage,
        public readonly float $min_length_ratio,
        public readonly int $max_candidates_per_word,
        public readonly float $early_stop_threshold,
    ) {}
}
