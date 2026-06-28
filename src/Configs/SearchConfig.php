<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Configs;

use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SearchConfig implements SearchConfigInterface
{
    private const DEFAULT_GRAM_WEIGHTS = [
        2 => 0.3,
        3 => 0.5,
        4 => 0.7,
        'default' => 1.0,
    ];

    private const DEFAULT_MIN_LENGTH_RATIO = 0.6;

    private const DEFAULT_MAX_CANDIDATES = 200;

    private const DEFAULT_EARLY_STOP_THRESHOLD = 0.95;

    private const DEFAULT_MIN_NGRAM_LENGTH = 2;

    private const DEFAULT_MAX_NGRAM_LENGTH = 4;

    private const DEFAULT_MAX_PENALTY = 0.5;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getGramWeight(int $length): float
    {
        $weights = $this->config->get('search.gram_weights', self::DEFAULT_GRAM_WEIGHTS);

        return $weights[$length] ?? $weights['default'] ?? self::DEFAULT_GRAM_WEIGHTS['default'];
    }

    public function getMinLengthRatio(): float
    {
        return $this->config->get('search.min_length_ratio', self::DEFAULT_MIN_LENGTH_RATIO);
    }

    public function getMaxCandidates(): int
    {
        return $this->config->get('search.max_candidates', self::DEFAULT_MAX_CANDIDATES);
    }

    public function getEarlyStopThreshold(): float
    {
        return $this->config->get('search.early_stop_threshold', self::DEFAULT_EARLY_STOP_THRESHOLD);
    }

    public function getMinNgramLength(): int
    {
        return $this->config->get('search.min_ngram_length', self::DEFAULT_MIN_NGRAM_LENGTH);
    }

    public function getMaxNgramLength(): int
    {
        return $this->config->get('search.max_ngram_length', self::DEFAULT_MAX_NGRAM_LENGTH);
    }

    public function getMaxPenalty(): float
    {
        return $this->config->get('search.max_penalty', self::DEFAULT_MAX_PENALTY);
    }

    public function getStopWords(): array
    {
        return $this->config->get('search.stop_words', []);
    }

    public function isStopWord(string $word): bool
    {
        return in_array(strtolower($word), $this->getStopWords(), true);
    }

    public function getGramWeights(): array
    {
        return $this->config->get('search.gram_weights', self::DEFAULT_GRAM_WEIGHTS);
    }
}
