<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\MatchResultCollection;
use AndyDefer\LaravelSearch\Collections\QueryWordsCollection;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class QueryProcessorService implements QueryProcessorInterface
{
    private const DEFAULT_MAX_GRAM_LENGTH = 3;

    private const DEFAULT_MIN_WORD_LENGTH = 2;

    private const DEFAULT_SCORE_DEFAULT_VALUE = 1.0;

    private const DEFAULT_MAX_SCORE_PERCENTAGE = 100.0;

    public function __construct(
        private readonly SearchConfigInterface $config,
        private readonly TextNormalizerInterface $normalizer,
        private readonly NgramInterface $ngram,
    ) {}

    public function process(StringVO $query): QueryWordsCollection
    {
        $normalized = $this->normalizer->normalize($query->getValue());
        $words = explode(' ', $normalized);

        $collection = new QueryWordsCollection;

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            // Filtrer les stopwords
            if ($this->config->isStopWord($word)) {
                continue;
            }

            $ngrams = $this->ngram->generate($word)->toArray();

            $collection->add(QueryWordRecord::from([
                'original' => StringVO::from($word),
                'normalized' => $this->normalizer->normalize($word),
                'ngrams' => StringTypedCollection::from($ngrams),
            ]));
        }

        return $collection;
    }

    public function computeScore(QueryWordsCollection $query_words, ItemWordsCollection $item_words): ?MatchResultCollection
    {
        $results = new MatchResultCollection;

        // Grouper item_words par index
        $itemsByIndex = [];
        foreach ($item_words as $idx => $item) {
            // Si pas de search_index, utiliser un index factice basé sur la position
            $searchIndex = $item->search_index;
            $indexId = $searchIndex?->id->getValue() ?? 'index_'.$idx;

            if (! isset($itemsByIndex[$indexId])) {
                $itemsByIndex[$indexId] = [
                    'index' => $searchIndex,
                    'items' => [],
                ];
            }
            $itemsByIndex[$indexId]['items'][] = $item;
        }

        foreach ($itemsByIndex as $data) {
            $totalPercentage = 0;
            $wordCount = 0;

            foreach ($query_words as $query_data) {
                $bestScore = 0;
                $bestMaxPossible = 0;

                foreach ($data['items'] as $item_data) {
                    // Vérifier d'abord le match exact
                    if ($item_data->normalized->equals($query_data->normalized)) {
                        $bestScore = $item_data->max_score->getValue();
                        $bestMaxPossible = $item_data->max_score->getValue();
                        break;
                    }

                    $score = $this->calculateScore($query_data, $item_data)->getValue();
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMaxPossible = $item_data->max_score->getValue();
                    }
                }

                if ($bestScore > 0 && $bestMaxPossible > 0) {
                    $percentage = min(round(($bestScore / $bestMaxPossible) * self::DEFAULT_MAX_SCORE_PERCENTAGE, 2), self::DEFAULT_MAX_SCORE_PERCENTAGE);
                    $totalPercentage += $percentage;
                    $wordCount++;
                }
            }

            if ($wordCount > 0) {
                $avgPercentage = $totalPercentage / $wordCount;
                $results->add(MatchResultRecord::from([
                    'search_index' => $data['index'],
                    'score' => FloatVO::from(self::DEFAULT_SCORE_DEFAULT_VALUE),
                    'max_possible' => FloatVO::from(self::DEFAULT_SCORE_DEFAULT_VALUE),
                    'percentage' => FloatVO::from($avgPercentage),
                ]));
            }
        }

        if ($results->isEmpty()) {
            return null;
        }

        return $results;
    }

    public function sortResults(MatchResultCollection $results): MatchResultCollection
    {
        return $results->sortByRelevance();
    }

    private function calculateScore(QueryWordRecord $query_data, ItemWordRecord $item_data): FloatVO
    {
        $query_word = $query_data->normalized;
        $item_word = $item_data->normalized;
        $max_possible = $item_data->max_score->getValue();

        if ($item_word->equals($query_word)) {
            return FloatVO::from($max_possible);
        }

        $score = 0.0;
        $query_ngrams = $query_data->ngrams->toArray();
        $item_ngrams = $item_data->ngrams->toArray();

        foreach ($query_ngrams as $gram) {
            if (in_array($gram, $item_ngrams, true)) {
                $score += $this->config->getGramWeight(strlen($gram));
            }
        }

        $penalty = $this->calculateLevenshteinPenalty($query_word, $item_word);
        $score = $score * (1 - $penalty);

        return FloatVO::from($score);
    }

    private function calculateLevenshteinPenalty(StringVO $word1, StringVO $word2): float
    {
        $max_length = max($word1->length(), $word2->length());
        if ($max_length === 0) {
            return 0.0;
        }

        $distance = levenshtein($word1->getValue(), $word2->getValue());

        return min($this->config->getMaxPenalty(), $distance / $max_length);
    }

    /**
     * Calcule le score maximum possible pour un mot
     */
    public function calculateMaxScore(string $word): FloatVO
    {
        $length = strlen($word);
        if ($length < self::DEFAULT_MIN_WORD_LENGTH) {
            return FloatVO::from(self::DEFAULT_SCORE_DEFAULT_VALUE);
        }

        $maxScore = 0.0;
        for ($i = 0; $i < $length - 1; $i++) {
            $gramLength = min(self::DEFAULT_MAX_GRAM_LENGTH, $length - $i);
            $maxScore += $this->config->getGramWeight($gramLength);
        }

        return FloatVO::from(max($maxScore, self::DEFAULT_SCORE_DEFAULT_VALUE));
    }
}
