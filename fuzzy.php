<?php

declare(strict_types=1);

/**
 * Fuzzy search engine for matching strings using n-gram analysis.
 *
 * This package provides a pure PHP implementation of a fuzzy search algorithm
 * that matches strings based on character n-grams (2, 3, and 4-grams) with
 * weighted scoring. Includes pre-filtering, caching, and performance optimizations.
 *
 * @author Your Name
 *
 * @version 1.0.0
 */

namespace FuzzySearch;

require_once './data.php';

/**
 * Handles string normalization and cleaning operations.
 *
 * Removes accents, diacritics, and special characters while normalizing whitespace.
 * Results are cached for performance when processing the same string multiple times.
 */
class StringNormalizer
{
    /**
     * @var array<string, string> Cache for normalized strings
     */
    private array $cache = [];

    /**
     * Mapping of accented characters to their ASCII equivalents.
     *
     * @var array<string, string>
     */
    private const DIACRITICS = [
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

    /**
     * Removes special characters from a string.
     *
     * Keeps only alphanumeric characters, spaces, apostrophes, and hyphens.
     *
     * @param  string  $input  The string to clean
     * @return string The string with special characters replaced by spaces
     */
    public function removeSpecialChars(string $input): string
    {
        return preg_replace('/[^a-zA-Z0-9\s\'-]/u', ' ', $input) ?? '';
    }

    /**
     * Converts accented characters to their ASCII equivalents.
     *
     * @param  string  $input  The string to normalize
     * @return string The string with accents removed
     */
    public function removeAccents(string $input): string
    {
        return strtr($input, self::DIACRITICS);
    }

    /**
     * Completely cleans a string with caching.
     *
     * Applies accent removal, special character filtering, and whitespace normalization.
     * Results are cached for identical input strings.
     *
     * @param  string  $input  The string to clean
     * @return string The fully cleaned string
     */
    public function clean(string $input): string
    {
        if (! isset($this->cache[$input])) {
            $noAccents = $this->removeAccents($input);
            $noSpecialChars = $this->removeSpecialChars($noAccents);
            $normalized = preg_replace('/\s+/', ' ', trim($noSpecialChars)) ?? '';
            $this->cache[$input] = $normalized;
        }

        return $this->cache[$input];
    }

    /**
     * Clears the internal cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}

/**
 * Generates and weights n-grams for string comparison.
 *
 * Creates character sequences of lengths 2, 3, and 4 (bigrams, trigrams, 4-grams)
 * and assigns weights based on gram length for scoring.
 */
class NGramEngine
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 4;

    /**
     * @var array<string, array<int, string>> Cache for word n-grams
     */
    private array $gramCache = [];

    /**
     * @var array<string, float> Cache for maximum possible scores
     */
    private array $scoreCache = [];

    /**
     * Generates all n-grams for a word.
     *
     * Creates overlapping character sequences of lengths 2, 3, and 4.
     * Returns unique grams only.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generate(string $word): array
    {
        $length = strlen($word);
        $grams = [];

        for ($gramLength = self::MIN_LENGTH; $gramLength <= self::MAX_LENGTH; $gramLength++) {
            if ($gramLength > $length) {
                continue;
            }

            for ($i = 0; $i <= $length - $gramLength; $i++) {
                $grams[] = substr($word, $i, $gramLength);
            }
        }

        return array_values(array_unique($grams));
    }

    /**
     * Generates n-grams with caching.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generateWithCache(string $word): array
    {
        if (! isset($this->gramCache[$word])) {
            $this->gramCache[$word] = $this->generate($word);
        }

        return $this->gramCache[$word];
    }

    /**
     * Calculates the weight of an n-gram based on its length.
     *
     * Longer grams are weighted more heavily as they are more specific.
     *
     * @param  int  $length  The n-gram length (2-4)
     * @return float The weight value
     */
    public function getWeight(int $length): float
    {
        return $length + (($length - 1) * 0.5);
    }

    /**
     * Calculates the maximum possible score for a word.
     *
     * Sum of weights for all unique n-grams in the word.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScore(string $word): float
    {
        $grams = $this->generate($word);
        $maxScore = 0.0;

        foreach ($grams as $gram) {
            $maxScore += $this->getWeight(strlen($gram));
        }

        return round($maxScore, 1);
    }

    /**
     * Gets maximum possible score with caching.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScoreWithCache(string $word): float
    {
        if (! isset($this->scoreCache[$word])) {
            $this->scoreCache[$word] = $this->getMaxScore($word);
        }

        return $this->scoreCache[$word];
    }

    /**
     * Clears all internal caches.
     */
    public function clearCache(): void
    {
        $this->gramCache = [];
        $this->scoreCache = [];
    }
}

/**
 * Compares words using n-gram analysis with performance optimizations.
 *
 * Implements pre-filtering, candidate selection, and early stopping
 * to efficiently find the best matching word.
 */
class WordComparator
{
    private const MIN_LENGTH_RATIO = 0.5;

    private const MAX_CANDIDATES = 5;

    private const EARLY_STOP_THRESHOLD = 0.95;

    private NGramEngine $ngramEngine;

    public function __construct(NGramEngine $ngramEngine)
    {
        $this->ngramEngine = $ngramEngine;
    }

    /**
     * Counts matching letters between two words.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return int Number of matching characters
     */
    public function countMatchingLetters(string $word1, string $word2): int
    {
        $letters1 = count_chars($word1, 1);
        $letters2 = count_chars($word2, 1);
        $matches = 0;

        foreach ($letters1 as $char => $count) {
            if (isset($letters2[$char])) {
                $matches += min($count, $letters2[$char]);
            }
        }

        return $matches;
    }

    /**
     * Applies quick length-based pre-filtering.
     *
     * @param  string  $queryWord  The query word
     * @param  string  $itemWord  The candidate word
     * @return bool True if the candidate passes the length filter
     */
    public function passesLengthFilter(string $queryWord, string $itemWord): bool
    {
        $queryLength = strlen($queryWord);
        $itemLength = strlen($itemWord);

        if ($itemLength < $queryLength * self::MIN_LENGTH_RATIO) {
            return false;
        }

        if ($itemLength < 2 || $queryLength < 2) {
            return false;
        }

        return true;
    }

    /**
     * Calculates match score using precomputed word data.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<string, mixed>  $itemData  Precomputed item word data
     * @return float The calculated score
     */
    public function calculateScore(array $queryData, array $itemData): float
    {
        $queryWord = $queryData['normalized'];
        $itemWord = $itemData['normalized'];
        $maxPossible = $itemData['max_score'];

        // Perfect match detection
        if (str_contains($itemWord, $queryWord)) {
            return $maxPossible;
        }

        $score = 0.0;

        foreach ($queryData['ngrams'] as $gram) {
            if (str_contains($itemWord, $gram)) {
                $score += $this->ngramEngine->getWeight(strlen($gram));
            }

            // Early stop for high scores
            if ($score >= $maxPossible * self::EARLY_STOP_THRESHOLD) {
                break;
            }
        }

        return $score;
    }

    /**
     * Finds the best matching word for a query from a list of candidates.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<int, array<string, mixed>>  $itemWords  List of precomputed item words
     * @return array<string, float> Best match results with score and percentage
     */
    public function findBestMatch(array $queryData, array $itemWords): array
    {
        $queryWord = $queryData['normalized'];
        $candidates = [];

        // Pre-filter candidates
        foreach ($itemWords as $index => $itemData) {
            if (! $this->passesLengthFilter($queryWord, $itemData['normalized'])) {
                continue;
            }

            $letterMatches = $this->countMatchingLetters($queryWord, $itemData['normalized']);
            if ($letterMatches > 0) {
                $candidates[] = ['index' => $index, 'matches' => $letterMatches];
            }
        }

        if (empty($candidates)) {
            return ['score' => 0.0, 'max_possible' => 0.0, 'percentage' => 0.0];
        }

        // Keep only top candidates
        usort($candidates, fn ($a, $b) => $b['matches'] <=> $a['matches']);
        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES);

        $bestScore = 0.0;
        $bestMaxPossible = 0.0;
        $bestPercentage = 0.0;

        foreach ($candidates as $candidate) {
            $itemData = $itemWords[$candidate['index']];
            $score = $this->calculateScore($queryData, $itemData);
            $maxPossible = $itemData['max_score'];

            $queryLength = max(strlen($queryWord), 1);
            $wordLength = max(strlen($itemData['normalized']), 1);

            if ($maxPossible > 0) {
                $percentage = ($score / $queryLength) * 100 / ($maxPossible / $wordLength);
                $percentage = min(round($percentage, 2), 100.0);
            } else {
                $percentage = 0.0;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMaxPossible = $maxPossible;
                $bestPercentage = $percentage;
            }
        }

        return [
            'score' => $bestScore,
            'max_possible' => $bestMaxPossible,
            'percentage' => $bestPercentage,
        ];
    }
}

/**
 * Applies pre-filtering to reduce candidates before detailed comparison.
 *
 * Uses letter frequency analysis to quickly eliminate unlikely matches.
 */
class PreFilter
{
    private const MIN_LETTER_MATCH_PERCENTAGE = 30;

    private StringNormalizer $normalizer;

    public function __construct(StringNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * Checks if an item passes the letter frequency filter.
     *
     * @param  string  $item  The item text
     * @param  string  $query  The search query
     * @return bool True if the item has enough matching letters
     */
    public function passes(string $item, string $query): bool
    {
        $cleanedItem = $this->normalizer->clean($item);
        $cleanedQuery = $this->normalizer->clean($query);

        $itemLetters = array_unique(str_split(preg_replace('/\s+/', '', $cleanedItem)));
        $queryLetters = array_unique(str_split(preg_replace('/\s+/', '', $cleanedQuery)));

        if (empty($queryLetters)) {
            return false;
        }

        $matchingLetters = 0;
        foreach ($queryLetters as $letter) {
            if (in_array($letter, $itemLetters, true)) {
                $matchingLetters++;
            }
        }

        $percentage = ($matchingLetters / count($queryLetters)) * 100;

        return $percentage >= self::MIN_LETTER_MATCH_PERCENTAGE;
    }
}

/**
 * Preprocesses and stores dataset for optimized searching.
 *
 * Converts all items into precomputed word data structures
 * to avoid redundant processing during searches.
 */
class DatasetPreprocessor
{
    /**
     * @var array<int, string> Raw dataset
     */
    private array $rawData = [];

    /**
     * @var array<string, array<int, array<string, mixed>>> Preprocessed data
     */
    private array $preprocessed = [];

    private StringNormalizer $normalizer;

    private NGramEngine $ngramEngine;

    public function __construct(StringNormalizer $normalizer, NGramEngine $ngramEngine)
    {
        $this->normalizer = $normalizer;
        $this->ngramEngine = $ngramEngine;
    }

    /**
     * Sets and preprocesses a new dataset.
     *
     * @param  array<int, string>  $data  The dataset to process
     */
    public function setData(array $data): void
    {
        $this->rawData = $data;
        $this->preprocessed = [];
        $this->processAllItems();
    }

    /**
     * Returns the raw dataset.
     *
     * @return array<int, string>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Returns the preprocessed dataset.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getPreprocessed(): array
    {
        return $this->preprocessed;
    }

    /**
     * Processes all items in the dataset.
     */
    private function processAllItems(): void
    {
        foreach ($this->rawData as $item) {
            $cleaned = $this->normalizer->clean($item);
            $words = $this->splitWords($cleaned);

            $processedWords = [];
            foreach ($words as $word) {
                $normalized = strtolower($word);
                $processedWords[] = [
                    'original' => $word,
                    'normalized' => $normalized,
                    'max_score' => $this->ngramEngine->getMaxScoreWithCache($normalized),
                    'ngrams' => $this->ngramEngine->generateWithCache($normalized),
                ];
            }
            $this->preprocessed[$item] = $processedWords;
        }
    }

    /**
     * Splits a cleaned string into individual words.
     *
     * @param  string  $string  The cleaned string
     * @return array<int, string> Array of words
     */
    private function splitWords(string $string): array
    {
        $words = explode(' ', $string);

        return array_values(array_filter($words, fn (string $word): bool => $word !== ''));
    }
}

/**
 * Processes search queries and computes match scores.
 *
 * Handles query preprocessing, score calculation, and result sorting.
 */
class QueryProcessor
{
    private StringNormalizer $normalizer;

    private NGramEngine $ngramEngine;

    private WordComparator $comparator;

    public function __construct(
        StringNormalizer $normalizer,
        NGramEngine $ngramEngine,
        WordComparator $comparator
    ) {
        $this->normalizer = $normalizer;
        $this->ngramEngine = $ngramEngine;
        $this->comparator = $comparator;
    }

    /**
     * Processes a query string into word data.
     *
     * @param  string  $query  The search query
     * @return array<int, array<string, mixed>> Processed query words
     */
    public function process(string $query): array
    {
        $cleaned = $this->normalizer->clean($query);
        $words = $this->splitWords($cleaned);

        if (empty($words)) {
            return [];
        }

        $processedWords = [];
        foreach ($words as $word) {
            $processedWords[] = [
                'original' => $word,
                'normalized' => strtolower($word),
                'ngrams' => $this->ngramEngine->generateWithCache(strtolower($word)),
            ];
        }

        return $processedWords;
    }

    /**
     * Computes the match score between query words and item words.
     *
     * @param  array<int, array<string, mixed>>  $queryWords  Processed query words
     * @param  array<int, array<string, mixed>>  $itemWords  Processed item words
     * @return array<string, float>|null Match results or null if no match
     */
    public function computeScore(array $queryWords, array $itemWords): ?array
    {
        $totalScore = 0.0;
        $totalMaxPossible = 0.0;
        $totalPercentage = 0.0;
        $wordCount = 0;

        foreach ($queryWords as $queryData) {
            $bestMatch = $this->comparator->findBestMatch($queryData, $itemWords);

            if ($bestMatch['score'] > 0) {
                $totalScore += $bestMatch['score'];
                $totalMaxPossible += $bestMatch['max_possible'];
                $totalPercentage += $bestMatch['percentage'];
                $wordCount++;
            }
        }

        if ($totalScore === 0.0 || $wordCount === 0) {
            return null;
        }

        return [
            'score' => $totalScore,
            'max_possible' => $totalMaxPossible,
            'percentage' => round($totalPercentage / $wordCount, 2),
        ];
    }

    /**
     * Sorts results by relevance.
     *
     * Sorts by percentage descending, then score descending.
     *
     * @param  array<int, array<string, mixed>>  &$results  Results to sort
     */
    public function sortResults(array &$results): void
    {
        usort($results, function (array $a, array $b): int {
            if ($b['percentage'] === $a['percentage']) {
                return $b['score'] <=> $a['score'];
            }

            return $b['percentage'] <=> $a['percentage'];
        });
    }

    /**
     * Splits a cleaned string into individual words.
     *
     * @param  string  $string  The cleaned string
     * @return array<int, string> Array of words
     */
    private function splitWords(string $string): array
    {
        $words = explode(' ', $string);

        return array_values(array_filter($words, fn (string $word): bool => $word !== ''));
    }
}

/**
 * Main fuzzy search engine.
 *
 * Orchestrates all components to provide a complete fuzzy search solution
 * with preprocessing, filtering, and scoring.
 *
 * @example
 * $engine = new SearchEngine(['John Doe', 'Jane Smith']);
 * $results = $engine->search('Jon Doe', 3);
 */
class SearchEngine
{
    private DatasetPreprocessor $preprocessor;

    private QueryProcessor $queryProcessor;

    private PreFilter $preFilter;

    /**
     * @param  array<int, string>  $data  Initial dataset
     */
    public function __construct(array $data = [])
    {
        $normalizer = new StringNormalizer;
        $ngramEngine = new NGramEngine;
        $comparator = new WordComparator($ngramEngine);

        $this->preprocessor = new DatasetPreprocessor($normalizer, $ngramEngine);
        $this->queryProcessor = new QueryProcessor($normalizer, $ngramEngine, $comparator);
        $this->preFilter = new PreFilter($normalizer);

        if (! empty($data)) {
            $this->preprocessor->setData($data);
        }
    }

    /**
     * Replaces the entire dataset with a new one.
     *
     * @param  array<int, string>  $data  New dataset
     */
    public function setData(array $data): self
    {
        $this->preprocessor->setData($data);

        return $this;
    }

    /**
     * Returns the current dataset.
     *
     * @return array<int, string>
     */
    public function getData(): array
    {
        return $this->preprocessor->getRawData();
    }

    /**
     * Clears all internal caches to free memory.
     */
    public function clearCache(): void
    {
        $normalizer = new StringNormalizer;
        $ngramEngine = new NGramEngine;
        $comparator = new WordComparator($ngramEngine);

        $this->queryProcessor = new QueryProcessor($normalizer, $ngramEngine, $comparator);
        $this->preFilter = new PreFilter($normalizer);
    }

    /**
     * Searches the dataset for items matching the query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results to return
     * @return array<int, array<string, mixed>> Array of results with scores
     */
    public function search(string $query, int $limit = 5): array
    {
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $results = [];

        foreach ($this->preprocessor->getPreprocessed() as $item => $itemWords) {
            if (! $this->preFilter->passes($item, $query)) {
                continue;
            }

            $score = $this->queryProcessor->computeScore($processedQuery, $itemWords);

            if ($score !== null) {
                $results[] = array_merge(['name' => $item], $score);
            }
        }

        $this->queryProcessor->sortResults($results);

        return array_slice($results, 0, $limit);
    }
}

// Script execution
if (isset($artistes)) {
    $searchEngine = new SearchEngine($artistes);

    $query = $argv[1] ?? 'Lucas Leroy';
    $results = $searchEngine->search($query, 5);

    echo "Top 5 résultats pour '$query' :\n";
    echo str_repeat('=', 80)."\n";
    foreach ($results as $index => $result) {
        $isMax = ($result['percentage'] == 100) ? ' - [MAX POSSIBLE]' : '';
        echo ($index + 1).'. '.$result['name'].
            ' (score: '.$result['score'].
            ' / max: '.$result['max_possible'].
            ') - Pertinence: '.$result['percentage'].'%'.
            $isMax."\n";
    }
}
