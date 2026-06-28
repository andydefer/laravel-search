<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\MatchResultCollection;
use AndyDefer\LaravelSearch\Collections\QueryWordsCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class QueryProcessorService implements QueryProcessorInterface
{
    public function __construct(
        private readonly SearchConfig $config,
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
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

            $ngrams = $this->ngramService->generate($word)->toArray();

            $collection->add(QueryWordRecord::from([
                'original' => StringVO::from($word),
                'normalized' => $this->normalizer->normalize($word),
                'ngrams' => Sequential::from($ngrams),
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
                    $percentage = min(round(($bestScore / $bestMaxPossible) * 100, 2), 100.0);
                    $totalPercentage += $percentage;
                    $wordCount++;
                }
            }

            if ($wordCount > 0) {
                $avgPercentage = $totalPercentage / $wordCount;
                $results->add(MatchResultRecord::from([
                    'search_index' => $data['index'],
                    'score' => FloatVO::from(1.0),
                    'max_possible' => FloatVO::from(1.0),
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

        return min(0.5, $distance / $max_length);
    }

    /**
     * Calcule le score maximum possible pour un mot
     */
    public function calculateMaxScore(string $word): FloatVO
    {
        $length = strlen($word);
        if ($length < 2) {
            return FloatVO::from(1.0);
        }

        $maxScore = 0.0;
        for ($i = 0; $i < $length - 1; $i++) {
            $gramLength = min(3, $length - $i);
            $maxScore += $this->config->getGramWeight($gramLength);
        }

        return FloatVO::from(max($maxScore, 1.0));
    }
}
