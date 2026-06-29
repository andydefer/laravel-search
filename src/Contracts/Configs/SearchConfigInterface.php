<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Configs;

interface SearchConfigInterface
{
    public function getGramWeight(int $length): float;

    public function getMinLengthRatio(): float;

    public function getMaxCandidates(): int;

    public function getEarlyStopThreshold(): float;

    public function getMinNgramLength(): int;

    public function getMaxNgramLength(): int;

    public function getMaxPenalty(): float;

    public function getStopWords(): array;

    public function isStopWord(string $word): bool;

    public function getGramWeights(): array;

    public function getMaxCandidatesAfterFilter(): int;

    public function getMinCommonBigrams(): int;

    public function getSearchablePaths(): array;
}
