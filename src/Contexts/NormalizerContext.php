<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contexts;

/**
 * Context for string normalization.
 * Mutable state that travels through the normalization pipeline.
 */
final class NormalizerContext
{
    private const DIACRITICS_MAP = [
        'Š' => 'S',
        'š' => 's',
        'Ž' => 'Z',
        'ž' => 'z',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'A',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'B',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'a',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'b',
        'ÿ' => 'y',
    ];

    private array $normalizeCache = [];

    public function getNormalized(string $string): ?string
    {
        return $this->normalizeCache[$string] ?? null;
    }

    public function setNormalized(string $original, string $normalized): void
    {
        $this->normalizeCache[$original] = $normalized;
    }

    public function has(string $string): bool
    {
        return isset($this->normalizeCache[$string]);
    }

    public function removeAccents(string $string): string
    {
        return strtr($string, self::DIACRITICS_MAP);
    }

    public function clearCache(): void
    {
        $this->normalizeCache = [];
    }
}
