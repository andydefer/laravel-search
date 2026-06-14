<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelSearch\ValueObjects\ProcessedQueryWordVO;

/**
 * Collection of processed query words.
 */
final class ProcessedQueryWordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ProcessedQueryWordVO::class);
    }

    public function getQueryLetters(): array
    {
        $allLetters = [];
        foreach ($this->items as $word) {
            $letters = str_split(preg_replace('/\s+/', '', $word->getOriginal()));
            $allLetters = array_merge($allLetters, $letters);
        }

        return array_values(array_unique($allLetters));
    }

    public function getQueryText(): string
    {
        $words = [];
        foreach ($this->items as $word) {
            $words[] = $word->getOriginal();
        }

        return implode(' ', $words);
    }

    public function getNormalizedText(): string
    {
        $words = [];
        foreach ($this->items as $word) {
            $words[] = $word->getNormalized();
        }

        return implode(' ', $words);
    }

    public function getFirst(): ?ProcessedQueryWordVO
    {
        return $this->items[0] ?? null;
    }

    public function getLast(): ?ProcessedQueryWordVO
    {
        return $this->items[$this->count() - 1] ?? null;
    }
}
