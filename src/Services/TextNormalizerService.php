<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\LaravelSearch\Configs\SearchConfig;

final class TextNormalizerService
{
    /**
     * UNIQUEMENT les articles élidés l' et d'
     * PAS j', c', t', m', n', qu' (ce sont des pronoms/verbes)
     */
    private const ELIDED_ARTICLES = [
        "l'", "L'",
        "d'", "D'",
    ];

    private const DIACRITICS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'Ǻ' => 'A', 'ǻ' => 'a',
        'Ç' => 'C', 'ç' => 'c',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ý' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
        'Æ' => 'AE', 'æ' => 'ae',
        'Œ' => 'OE', 'œ' => 'oe',
        'ß' => 'ss',
        'α' => 'a', 'Α' => 'A',
        'β' => 'b', 'Β' => 'B',
        'γ' => 'g', 'Γ' => 'G',
        'δ' => 'd', 'Δ' => 'D',
        'ε' => 'e', 'Ε' => 'E',
        'ζ' => 'z', 'Ζ' => 'Z',
        'η' => 'e', 'Η' => 'E',
        'θ' => 'th', 'Θ' => 'Th',
        'ι' => 'i', 'Ι' => 'I',
        'κ' => 'k', 'Κ' => 'K',
        'λ' => 'l', 'Λ' => 'L',
        'μ' => 'm', 'Μ' => 'M',
        'ν' => 'n', 'Ν' => 'N',
        'ξ' => 'x', 'Ξ' => 'X',
        'ο' => 'o', 'Ο' => 'O',
        'π' => 'p', 'Π' => 'P',
        'ρ' => 'r', 'Ρ' => 'R',
        'σ' => 's', 'ς' => 's', 'Σ' => 'S',
        'τ' => 't', 'Τ' => 'T',
        'υ' => 'y', 'Υ' => 'Y',
        'φ' => 'f', 'Φ' => 'F',
        'χ' => 'ch', 'Χ' => 'Ch',
        'ψ' => 'ps', 'Ψ' => 'Ps',
        'ω' => 'o', 'Ω' => 'O',
        'а' => 'a', 'А' => 'A',
        'б' => 'b', 'Б' => 'B',
        'в' => 'v', 'В' => 'V',
        'г' => 'g', 'Г' => 'G',
        'д' => 'd', 'Д' => 'D',
        'е' => 'e', 'Е' => 'E',
        'ё' => 'yo', 'Ё' => 'Yo',
        'ж' => 'zh', 'Ж' => 'Zh',
        'з' => 'z', 'З' => 'Z',
        'и' => 'i', 'И' => 'I',
        'й' => 'y', 'Й' => 'Y',
        'к' => 'k', 'К' => 'K',
        'л' => 'l', 'Л' => 'L',
        'м' => 'm', 'М' => 'M',
        'н' => 'n', 'Н' => 'N',
        'о' => 'o', 'О' => 'O',
        'п' => 'p', 'П' => 'P',
        'р' => 'r', 'Р' => 'R',
        'с' => 's', 'С' => 'S',
        'т' => 't', 'Т' => 'T',
        'у' => 'u', 'У' => 'U',
        'ф' => 'f', 'Ф' => 'F',
        'х' => 'kh', 'Х' => 'Kh',
        'ц' => 'ts', 'Ц' => 'Ts',
        'ч' => 'ch', 'Ч' => 'Ch',
        'ш' => 'sh', 'Ш' => 'Sh',
        'щ' => 'shch', 'Щ' => 'Shch',
        'ъ' => '', 'Ъ' => '',
        'ы' => 'y', 'Ы' => 'Y',
        'ь' => '', 'Ь' => '',
        'э' => 'e', 'Э' => 'E',
        'ю' => 'yu', 'Ю' => 'Yu',
        'я' => 'ya', 'Я' => 'Ya',
        '©' => ' (c) ',
        '®' => ' (r) ',
        '™' => ' tm ',
        '°' => ' deg ',
        '±' => ' +- ',
        '×' => ' x ',
        '÷' => ' / ',
        '¼' => ' 1/4 ',
        '½' => ' 1/2 ',
        '¾' => ' 3/4 ',
        '•' => ' ',
        '·' => ' ',
        '…' => ' ... ',
    ];

    private const CURRENCY_SYMBOLS = [
        '€' => ' eur ',
        '£' => ' gbp ',
        '¥' => ' jpy ',
        '₤' => ' lire ',
        '₣' => ' franc ',
        '₧' => ' peseta ',
        '₪' => ' ils ',
        '₩' => ' won ',
        '₨' => ' rupee ',
        '₮' => ' tugrik ',
        '$' => ' usd ',
        '¢' => ' cent ',
        '₵' => ' cedi ',
    ];

    public function __construct(
        private SearchConfig $config
    ) {}

    public function normalize(string $text): string
    {
        // Remplacer les tirets par des espaces pour les mots composés
        $text = str_replace('-', ' ', $text);

        $text = $this->removeDiacritics($text);
        $text = $this->removeCurrencySymbols($text);
        $text = $this->removeElidedArticles($text);
        $text = $this->removeSpecialChars($text);
        $text = $this->normalizeSpaces($text);
        $text = mb_strtolower($text);

        return $text;
    }

    public function extractWords(string $text): array
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return [];
        }
        $words = array_filter(explode(' ', $normalized));

        return array_values($words);
    }

    /**
     * Supprime UNIQUEMENT les articles l' et d'
     * NE SUPPRIME PAS j', c', t', m', n', qu'
     */
    public function removeElidedArticles(string $text): string
    {
        foreach (self::ELIDED_ARTICLES as $article) {
            $text = preg_replace('/\b'.preg_quote($article, '/').'(\p{L}+)/u', '$1', $text);
        }

        return $text;
    }

    public function removeDiacritics(string $text): string
    {
        return strtr($text, self::DIACRITICS);
    }

    public function removeCurrencySymbols(string $text): string
    {
        return strtr($text, self::CURRENCY_SYMBOLS);
    }

    public function removeSpecialChars(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}\s\'-]/u', ' ', $text);

        return $text ?? '';
    }

    public function normalizeSpaces(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    public function hasSpecialChars(string $text): bool
    {
        return preg_match('/[^a-zA-Z0-9\s]/', $text) === 1;
    }

    public function removeShortWords(array $words, int $minLength = 2): array
    {
        return array_values(array_filter($words, function ($word) use ($minLength) {
            return mb_strlen($word) >= $minLength;
        }));
    }

    public function hasAccents(string $text): bool
    {
        return preg_match('/[À-ÿ]/u', $text) === 1;
    }

    public function removeNonAscii(string $text): string
    {
        return preg_replace('/[^\x00-\x7F]/', ' ', $text) ?? '';
    }

    public function normalizeApostrophes(string $text): string
    {
        $apostrophes = [
            '’' => "'",
            '‘' => "'",
            '‛' => "'",
            '′' => "'",
            '`' => "'",
        ];

        return strtr($text, $apostrophes);
    }

    public function clean(string $text): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
    }

    public function hasNonLatinCharacters(string $text): bool
    {
        return preg_match('/[^\x00-\x7F]/u', $text) === 1;
    }
}
