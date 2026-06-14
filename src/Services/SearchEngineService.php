<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\LaravelSearch\Collections\NgramCollection;
use AndyDefer\LaravelSearch\Collections\ProcessedQueryWordCollection;
use AndyDefer\LaravelSearch\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelSearch\Contexts\SearchContext;
use AndyDefer\LaravelSearch\Contexts\SearchEngineConfigContext;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchEngineServiceInterface;
use AndyDefer\LaravelSearch\Records\MatchScoreRecord;
use AndyDefer\LaravelSearch\Records\NormalizedWordRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\ValueObjects\MatchScoreVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramVO;
use AndyDefer\LaravelSearch\ValueObjects\NormalizedStringVO;
use AndyDefer\LaravelSearch\ValueObjects\NormalizedWordVO;
use AndyDefer\LaravelSearch\ValueObjects\ProcessedQueryWordVO;
use AndyDefer\LaravelSearch\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelSearch\ValueObjects\SearchResultVO;

final class SearchEngineService implements SearchEngineServiceInterface
{
    public function __construct(
        private readonly SearchEngineConfigContext $engineConfigContext,
        private readonly SearchConfigInterface $config,
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function setData(SearchContext $context, array $data): void
    {
        $context->setData($data);
    }

    public function preprocessData(SearchContext $context): void
    {
        foreach ($context->getRawData() as $item) {
            $normalizedString = new NormalizedStringVO($item);
            $cleanedItem = $this->cleanString($normalizedString->getValue());
            $words = $this->splitIntoWords($cleanedItem);

            foreach ($words as $word) {
                $normalized = strtolower($word);
                $wordVO = new NormalizedWordVO(
                    original: $word,
                    normalized: $normalized,
                    ngrams: $this->getNGrams($normalized),
                    maxScore: $this->getMaxPossibleScore($normalized),
                );
                $context->addPreprocessedItem($item, $wordVO);
            }
        }
    }

    public function search(SearchContext $context): SearchContext
    {
        $query = $context->getQuery();

        $cacheKey = $this->getCacheKey($query);
        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $this->hydrateContextFromCache($context, $cached);
                return $context;
            }
        }

        $processedQueryWords = $this->processQueryWords($query->getQuery());
        $matches = $this->computeMatches($context, $processedQueryWords);
        $topMatches = $matches->getTop($query->getLimit());

        foreach ($topMatches as $match) {
            $context->addResult(new SearchResultVO(
                item: $match->item,
                score: $match->score,
                maxPossible: $match->max_possible,
                percentage: $match->percentage,
            ));
        }

        $this->cache->set($cacheKey, $context->getResults()->toResultArray(), $this->config->getCacheTtl());

        return $context;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    private function getCacheKey(SearchQueryVO $query): string
    {
        return $this->config->getCachePrefix() . md5($query->getQuery() . '_' . $query->getLimit());
    }

    private function processQueryWords(string $query): ProcessedQueryWordCollection
    {
        $cleanedQuery = $this->cleanString($query);
        $words = $this->splitIntoWords($cleanedQuery);
        $collection = new ProcessedQueryWordCollection;

        foreach ($words as $word) {
            $normalized = strtolower($word);
            $collection->add(new ProcessedQueryWordVO(
                original: $word,
                normalized: $normalized,
                ngrams: $this->getNGrams($normalized),
            ));
        }

        return $collection;
    }

    private function computeMatches(SearchContext $context, ProcessedQueryWordCollection $queryWords): SearchResultRecordCollection
    {
        $matches = new SearchResultRecordCollection;

        foreach ($context->getPreprocessedData() as $itemData) {
            /** @var NormalizedWordRecord $itemData */
            $itemOriginal = $itemData->original;

            if (!$this->passesLetterFilter($itemOriginal, $queryWords)) {
                continue;
            }

            // Extraire tous les mots normalisés pour cet item
            $itemWords = $this->extractItemWords($itemData);

            $matchScore = $this->computeMatchScore($queryWords, $itemWords);

            if ($matchScore !== null && $matchScore->isValid()) {
                $matches->add(new SearchResultRecord(
                    item: $itemOriginal,
                    score: $matchScore->getScore(),
                    max_possible: $matchScore->getMaxPossible(),
                    percentage: $matchScore->getPercentage(),
                ));
            }
        }

        return $matches;
    }

    /**
     * Extrait tous les mots normalisés d'un item pré-traité
     */
    private function extractItemWords(NormalizedWordRecord $itemData): array
    {
        $words = [];

        // Un seul mot par enregistrement dans cette structure
        $words[] = [
            'normalized' => $itemData->normalized->getNormalized(),
            'max_score' => $itemData->normalized->getMaxScore(),
            'ngrams' => $itemData->normalized->getNgrams(),
        ];

        return $words;
    }

    private function computeMatchScore(ProcessedQueryWordCollection $queryWords, array $itemWords): ?MatchScoreVO
    {
        $totalScore = 0.0;
        $totalMaxPossible = 0.0;
        $totalPercentage = 0.0;
        $wordCount = 0;

        foreach ($queryWords as $queryWord) {
            $bestMatch = $this->findBestMatchingWord($queryWord, $itemWords);

            if ($bestMatch->score > 0) {
                $totalScore += $bestMatch->score;
                $totalMaxPossible += $bestMatch->max_possible;
                $totalPercentage += $bestMatch->percentage;
                $wordCount++;
            }
        }

        if ($totalScore === 0.0 || $wordCount === 0) {
            return null;
        }

        return new MatchScoreVO(
            score: $totalScore,
            maxPossible: $totalMaxPossible,
            percentage: round($totalPercentage / $wordCount, 2),
        );
    }

    private function findBestMatchingWord(ProcessedQueryWordVO $queryWord, array $itemWords): MatchScoreRecord
    {
        $queryNormalized = $queryWord->getNormalized();
        $bestScore = 0.0;
        $bestMaxPossible = 0.0;
        $bestPercentage = 0.0;

        $minLength = max(strlen($queryNormalized), $this->config->getMinQueryLength());

        foreach ($itemWords as $itemWordData) {
            $score = $this->calculateWordScore($queryWord, $itemWordData);
            $maxPossible = $itemWordData['max_score'];

            $queryLength = max(strlen($queryNormalized), $minLength);
            $wordLength = max(strlen($itemWordData['normalized']), $minLength);

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

        return new MatchScoreRecord(
            score: $bestScore,
            max_possible: $bestMaxPossible,
            percentage: $bestPercentage,
        );
    }

    private function calculateWordScore(ProcessedQueryWordVO $queryWord, array $itemWordData): float
    {
        $itemWord = $itemWordData['normalized'];
        $maxPossible = $itemWordData['max_score'];

        if (str_contains($itemWord, $queryWord->getNormalized())) {
            return $maxPossible;
        }

        $score = 0.0;
        foreach ($queryWord->getNgrams() as $ngram) {
            if (str_contains($itemWord, $ngram->getValue())) {
                $score += $ngram->getWeight();
            }

            if ($score >= $maxPossible * $this->engineConfigContext->getEarlyStopThreshold()) {
                break;
            }
        }

        return $score;
    }

    private function getNGrams(string $word): NgramCollection
    {
        $length = strlen($word);
        $collection = new NgramCollection;

        for ($gramLength = $this->engineConfigContext->getMinGramLength(); $gramLength <= $this->engineConfigContext->getMaxGramLength(); $gramLength++) {
            if ($gramLength > $length) {
                continue;
            }

            for ($i = 0; $i <= $length - $gramLength; $i++) {
                $ngramValue = substr($word, $i, $gramLength);
                $collection->add(new NgramVO($ngramValue, $gramLength));
            }
        }

        return $collection;
    }

    private function getMaxPossibleScore(string $word): float
    {
        return $this->getNGrams($word)->getTotalWeight();
    }

    private function cleanString(string $string): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9\s\'-]/u', ' ', $string) ?? '';
        return preg_replace('/\s+/', ' ', trim($cleaned)) ?? '';
    }

    private function splitIntoWords(string $string): array
    {
        $words = explode(' ', $string);
        return array_values(array_filter($words, fn(string $word): bool => $word !== ''));
    }

    private function passesLetterFilter(string $item, ProcessedQueryWordCollection $queryWords): bool
    {
        $normalizedString = new NormalizedStringVO($item);
        $cleanedItem = $this->cleanString($normalizedString->getValue());
        // Correction: Convertir en minuscules pour la comparaison
        $itemLetters = array_unique(str_split(strtolower(preg_replace('/\s+/', '', $cleanedItem))));

        $cleanedQuery = $this->cleanString($queryWords->getQueryText());
        // Correction: Convertir en minuscules pour la comparaison
        $queryLetters = array_unique(str_split(strtolower(preg_replace('/\s+/', '', $cleanedQuery))));

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
        return $percentage >= $this->engineConfigContext->getMinLettersMatchPercentage();
    }

    private function hydrateContextFromCache(SearchContext $context, array $cachedResults): void
    {
        foreach ($cachedResults as $result) {
            $context->addResult(new SearchResultVO(
                item: $result['name'],
                score: $result['score'],
                maxPossible: $result['max_possible'],
                percentage: $result['percentage'],
            ));
        }
    }
}
