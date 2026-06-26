<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\LaravelSearch\Configs\SearchConfig;

final class TextNormalizerService
{
    private SearchConfig $config;

    /**
     * Table de correspondance des caractères accentués et spéciaux
     */
    private const DIACRITICS = [
        // Accents latins - lettre A
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'Ǻ' => 'A', 'ǻ' => 'a',

        // Accents latins - lettre C
        'Ç' => 'C', 'ç' => 'c',

        // Accents latins - lettre E
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',

        // Accents latins - lettre I
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',

        // Accents latins - lettre N
        'Ñ' => 'N', 'ñ' => 'n',

        // Accents latins - lettre O
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',

        // Accents latins - lettre U
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',

        // Accents latins - lettre Y
        'Ý' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ÿ' => 'y',

        // Ligatures
        'Æ' => 'AE', 'æ' => 'ae',
        'Œ' => 'OE', 'œ' => 'oe',
        'ß' => 'ss',

        // Caractères grecs
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

        // Caractères cyrilliques
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

        // Symboles divers
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

    /**
     * Table des symboles monétaires avec leurs équivalents textuels
     */
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

    public function __construct(?SearchConfig $config = null)
    {
        $this->config = $config ?? new SearchConfig;
    }

    /**
     * Normalise un texte : minuscules, sans accents, sans caractères spéciaux
     */
    public function normalize(string $text): string
    {
        // 1. Supprimer les accents et caractères spéciaux (table de correspondance)
        $text = $this->removeDiacritics($text);

        // 2. Supprimer les symboles monétaires avec espace
        $text = $this->removeCurrencySymbols($text);

        // 3. Supprimer les caractères spéciaux (garde lettres, chiffres, espaces)
        $text = $this->removeSpecialChars($text);

        // 4. Normaliser les espaces
        $text = $this->normalizeSpaces($text);

        // 5. Mettre en minuscules
        $text = mb_strtolower($text);

        return $text;
    }

    /**
     * Normalise et extrait les mots d'un texte
     */
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
     * Supprime les accents et caractères diacritiques avec la table de correspondance
     */
    public function removeDiacritics(string $text): string
    {
        return strtr($text, self::DIACRITICS);
    }

    /**
     * Supprime les symboles monétaires et les remplace par leur équivalent textuel avec espaces
     */
    public function removeCurrencySymbols(string $text): string
    {
        return strtr($text, self::CURRENCY_SYMBOLS);
    }

    /**
     * Supprime les caractères spéciaux (garde lettres, chiffres, espaces)
     */
    public function removeSpecialChars(string $text): string
    {
        // Supprimer les caractères qui ne sont pas des lettres, chiffres ou espaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return $text ?? '';
    }

    /**
     * Normalise les espaces (supprime les espaces multiples, les espaces en début/fin)
     */
    public function normalizeSpaces(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    /**
     * Vérifie si le texte contient des caractères spéciaux
     */
    public function hasSpecialChars(string $text): bool
    {
        return preg_match('/[^a-zA-Z0-9\s]/', $text) === 1;
    }

    /**
     * Supprime les mots trop courts
     */
    public function removeShortWords(array $words, int $minLength = 2): array
    {
        return array_values(array_filter($words, function ($word) use ($minLength) {
            return mb_strlen($word) >= $minLength;
        }));
    }

    /**
     * Vérifie si le texte contient des caractères accentués
     */
    public function hasAccents(string $text): bool
    {
        $patterns = array_keys(self::DIACRITICS);
        foreach ($patterns as $pattern) {
            if (mb_strpos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supprime les caractères non-ASCII
     */
    public function removeNonAscii(string $text): string
    {
        return preg_replace('/[^\x00-\x7F]/', ' ', $text) ?? '';
    }
}
