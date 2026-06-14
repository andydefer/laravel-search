<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contexts;

use AndyDefer\LaravelSearch\Records\EngineRecord;

/**
 * Context for search engine configuration.
 * Mutable state that holds engine configuration.
 */
final class SearchEngineConfigContext
{
    private EngineRecord $engineConfig;

    public function __construct(EngineRecord $engineConfig)
    {
        $this->engineConfig = $engineConfig;
    }

    public function getEngineConfig(): EngineRecord
    {
        return $this->engineConfig;
    }

    public function setEngineConfig(EngineRecord $engineConfig): void
    {
        $this->engineConfig = $engineConfig;
    }

    public function getMinGramLength(): int
    {
        return $this->engineConfig->min_gram_length;
    }

    public function getMaxGramLength(): int
    {
        return $this->engineConfig->max_gram_length;
    }

    public function getMinLettersMatchPercentage(): int
    {
        return $this->engineConfig->min_letters_match_percentage;
    }

    public function getMinLengthRatio(): float
    {
        return $this->engineConfig->min_length_ratio;
    }

    public function getMaxCandidatesPerWord(): int
    {
        return $this->engineConfig->max_candidates_per_word;
    }

    public function getEarlyStopThreshold(): float
    {
        return $this->engineConfig->early_stop_threshold;
    }
}
