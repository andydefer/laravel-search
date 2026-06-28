<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

interface TextNormalizerInterface
{
    public function normalize(string $text): string;

    public function extractWords(string $text): array;

    public function removeElidedArticles(string $text): string;

    public function removeDiacritics(string $text): string;

    public function removeCurrencySymbols(string $text): string;

    public function removeSpecialChars(string $text): string;

    public function normalizeSpaces(string $text): string;

    public function hasSpecialChars(string $text): bool;

    public function removeShortWords(array $words, int $minLength = 2): array;

    public function hasAccents(string $text): bool;

    public function removeNonAscii(string $text): string;

    public function normalizeApostrophes(string $text): string;

    public function clean(string $text): string;

    public function hasNonLatinCharacters(string $text): bool;
}
