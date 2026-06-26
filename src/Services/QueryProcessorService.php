<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\ProcessedWordRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;

final class QueryProcessorService
{
    public function __construct(
        private readonly TextNormalizerService $normalizer,
    ) {}

    /**
     * Traite une requête en mots normalisés avec n-grams
     *
     * @return array<ProcessedWordRecord>
     */
    public function process(string $query): array
    {
        $cleaned = $this->normalizer->normalize($query);
        $words = $this->split_words($cleaned);

        if (empty($words)) {
            return [];
        }

        $processed_words = [];
        foreach ($words as $word) {
            $processed_words[] = new ProcessedWordRecord(
                original: $word,
                normalized: strtolower($word),
                ngrams: new NgramsVO($word),
            );
        }

        return $processed_words;
    }

    /**
     * Calcule le score entre les mots de la requête et les mots d'un item
     *
     * @param  array<ProcessedWordRecord>  $query_words
     * @param  array<string>  $item_words
     */
    public function compute_score(array $query_words, array $item_words): ?MatchResultRecord
    {
        if (empty($query_words) || empty($item_words)) {
            return null;
        }

        $total_score = 0.0;
        $total_max_possible = 0.0;
        $total_percentage = 0.0;
        $word_count = 0;

        foreach ($query_words as $query_word) {
            $best_match = $this->find_best_match($query_word, $item_words);

            if ($best_match->score > 0) {
                $total_score += $best_match->score;
                $total_max_possible += $best_match->max_possible;
                $total_percentage += $best_match->percentage;
                $word_count++;
            }
        }

        if ($total_score === 0.0 || $word_count === 0) {
            return null;
        }

        return new MatchResultRecord(
            score: $total_score / $word_count,
            max_possible: $total_max_possible / $word_count,
            percentage: round($total_percentage / $word_count, 2),
        );
    }

    /**
     * Trouve le meilleur match pour un mot de la requête
     */
    public function find_best_match(ProcessedWordRecord $query_word, array $item_words): MatchResultRecord
    {
        $query_text = $query_word->normalized;
        $query_length = strlen($query_text);
        $best_score = 0.0;
        $best_max_possible = 1.0;

        foreach ($item_words as $item_word) {
            $item_text = strtolower($item_word);
            $item_length = strlen($item_text);

            // Vérification rapide de la longueur
            if ($item_length < 2 || $query_length < 2) {
                continue;
            }

            // Correspondance exacte
            if ($item_text === $query_text) {
                return new MatchResultRecord(
                    score: 1.0,
                    max_possible: 1.0,
                    percentage: 100.0,
                );
            }

            // Comptage des lettres communes
            $letter_matches = $this->count_matching_letters($query_text, $item_text);
            if ($letter_matches === 0) {
                continue;
            }

            // Calcul du score avec n-grams
            $score = $this->calculate_ngram_score($query_word, $item_text);
            $max_possible = 1.0;

            if ($score > $best_score) {
                $best_score = $score;
                $best_max_possible = $max_possible;
            }
        }

        $percentage = $best_score > 0 ? round(($best_score / $best_max_possible) * 100, 2) : 0.0;

        return new MatchResultRecord(
            score: $best_score,
            max_possible: $best_max_possible,
            percentage: $percentage,
        );
    }

    /**
     * Compte les lettres communes entre deux mots
     */
    private function count_matching_letters(string $word1, string $word2): int
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
     * Calcule le score basé sur les n-grams
     */
    private function calculate_ngram_score(ProcessedWordRecord $query_word, string $item_word): float
    {
        $query_ngrams = $query_word->ngrams->toArray();
        $score = 0.0;

        foreach ($query_ngrams as $gram) {
            if (str_contains($item_word, $gram)) {
                $score += $this->get_gram_weight(strlen($gram));
            }
        }

        // Normalisation par rapport à la longueur
        $max_possible = count($query_ngrams);

        return $max_possible > 0 ? $score / $max_possible : 0.0;
    }

    /**
     * Poids des n-grams selon leur longueur
     */
    private function get_gram_weight(int $length): float
    {
        return match ($length) {
            2 => 0.3,
            3 => 0.5,
            4 => 0.7,
            default => 1.0,
        };
    }

    /**
     * Divise une chaîne en mots
     */
    private function split_words(string $text): array
    {
        $words = explode(' ', $text);

        return array_values(array_filter($words, fn ($word) => $word !== ''));
    }

    /**
     * Trie les résultats par pertinence
     *
     * @param  array<SearchResultRecord>  $results
     * @return array<SearchResultRecord>
     */
    public function sort_results(array $results): array
    {
        usort($results, function (SearchResultRecord $a, SearchResultRecord $b) {
            if ($b->percentage === $a->percentage) {
                return $b->score <=> $a->score;
            }

            return $b->percentage <=> $a->percentage;
        });

        return $results;
    }
}
