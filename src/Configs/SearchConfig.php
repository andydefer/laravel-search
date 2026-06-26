<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Configs;

final class SearchConfig
{
    private int $minNgramLength;
    private int $maxNgramLength;

    public function __construct(
        ?int $minNgramLength = null,
        ?int $maxNgramLength = null
    ) {
        $this->minNgramLength = $minNgramLength ?? 2;
        $this->maxNgramLength = $maxNgramLength ?? 4;
    }

    public function getMinNgramLength(): int
    {
        return $this->minNgramLength;
    }

    public function getMaxNgramLength(): int
    {
        return $this->maxNgramLength;
    }

    public function setMinNgramLength(int $length): self
    {
        $this->minNgramLength = $length;
        return $this;
    }

    public function setMaxNgramLength(int $length): self
    {
        $this->maxNgramLength = $length;
        return $this;
    }

    /**
     * Crée une configuration à partir d'un tableau
     */
    public static function fromArray(array $config): self
    {
        return new self(
            minNgramLength: $config['min_ngram_length'] ?? 2,
            maxNgramLength: $config['max_ngram_length'] ?? 4
        );
    }

    /**
     * Retourne la configuration par défaut
     */
    public static function default(): self
    {
        return new self();
    }
}