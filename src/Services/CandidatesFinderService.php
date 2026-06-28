<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Repositories\SearchIndexRepositoryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\CandidatesFinderInterface;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Contracts\Services\WordVectorParserInterface;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;

final class CandidatesFinderService implements CandidatesFinderInterface
{
    public function __construct(
        private readonly SearchIndexRepositoryInterface $repository,
        private readonly TextNormalizerInterface $normalizer,
        private readonly NgramInterface $ngramService,
        private readonly QueryProcessorInterface $queryProcessor,
        private readonly WordVectorParserInterface $wordVectorParser,
        private readonly SearchConfigInterface $config,
    ) {}

    public function findCandidates(SearchQueryRecord $query): ItemWordsCollection
    {
        $queryText = $query->query->getValue();
        $normalized = $this->normalizer->normalize($queryText);

        // Créer les vecteurs pour les mots de la requête
        $queryWords = explode(' ', $normalized);
        $queryWordVectors = $this->wordVectorParser->parse($queryWords);

        // Construire les filtres
        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $query->searchable_type,
            'searchable_id' => $query->searchable_id,
            'source_column' => $query->source_column,
        ]);

        // Phase 1: Recherche initiale par similarité (bigrams uniquement)
        $words = StringTypedCollection::from($queryWords);
        $ngrams = StringTypedCollection::from($this->ngramService->generateFromText($queryText)->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters);
        $searchIndexRecords = $this->repository->findCandidatesBySimilarity(
            $candidatesVO,
            $queryWordVectors,
            $this->config->getMinCommonBigrams()
        );

        // Phase 2: Filtrage intelligent par similarité
        $filteredIndexes = $this->filterBySimilarity($searchIndexRecords, $queryWordVectors);

        // Phase 3: Si trop de candidats, garder les plus pertinents
        if ($filteredIndexes->count() > $this->config->getMaxCandidatesAfterFilter()) {
            $filteredIndexes = $this->selectBestCandidates($filteredIndexes, $queryWordVectors, $this->config->getMaxCandidatesAfterFilter());
        }

        // Phase 4: Convertir en ItemWordsCollection
        return $this->convertToItemWordsCollection($filteredIndexes);
    }

    private function filterBySimilarity(SearchIndexCollection $searchIndexRecords, WordVectorCollection $queryVectors): SearchIndexCollection
    {
        $filtered = new SearchIndexCollection;

        foreach ($searchIndexRecords as $record) {
            // Reconstruire les vecteurs à partir du record
            $itemVectors = $record->item_words;
            $score = $this->calculateSimilarityScore($itemVectors, $queryVectors);

            if ($score >= $this->config->getMinCommonBigrams()) {
                $filtered->add($record);
            }
        }

        return $filtered;
    }

    private function calculateSimilarityScore(WordVectorCollection $itemVectors, WordVectorCollection $queryVectors): int
    {
        $totalMatches = 0;

        foreach ($queryVectors as $queryVector) {
            foreach ($itemVectors as $itemVector) {
                $commonBigrams = array_intersect($queryVector->bigrams->toArray(), $itemVector->bigrams->toArray());
                $totalMatches += count($commonBigrams);
            }
        }

        return (int) $totalMatches;
    }

    private function selectBestCandidates(SearchIndexCollection $searchIndexRecords, WordVectorCollection $queryVectors, int $limit): SearchIndexCollection
    {
        if ($searchIndexRecords->count() <= $limit) {
            return $searchIndexRecords;
        }

        $scoredCandidates = [];

        foreach ($searchIndexRecords as $record) {
            $itemVectors = $record->item_words;
            $score = $this->calculateDetailedScore($itemVectors, $queryVectors);
            $scoredCandidates[] = [
                'record' => $record,
                'score' => $score,
            ];
        }

        // Trier par score décroissant
        usort($scoredCandidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Prendre les N premiers
        $bestCandidates = array_slice($scoredCandidates, 0, $limit);

        $collection = new SearchIndexCollection;
        foreach ($bestCandidates as $candidate) {
            $collection->add($candidate['record']);
        }

        return $collection;
    }

    private function calculateDetailedScore(WordVectorCollection $itemVectors, WordVectorCollection $queryVectors): float
    {
        $totalScore = 0.0;

        foreach ($queryVectors as $queryVector) {
            foreach ($itemVectors as $itemVector) {
                // 1. Comparaison des unique_letters
                $uniqueLettersScore = $this->compareUniqueLetters($queryVector->unique_letters, $itemVector->unique_letters);

                // 2. Comparaison des metaphones
                $metaphoneScore = $this->compareMetaphone($queryVector->metaphone, $itemVector->metaphone);

                // 3. Comparaison des words
                $wordScore = $this->compareWords($queryVector->word, $itemVector->word);

                // 4. Comparaison des bigrams
                $bigramScore = $this->compareBigrams($queryVector->bigrams, $itemVector->bigrams);

                // Score pondéré
                $score = ($uniqueLettersScore * 0.3) + ($metaphoneScore * 0.3) + ($wordScore * 0.2) + ($bigramScore * 0.2);
                $totalScore += $score;
            }
        }

        return $totalScore;
    }

    private function compareUniqueLetters(StringTypedCollection $queryLetters, StringTypedCollection $itemLetters): float
    {
        $queryStr = implode('', $queryLetters->toArray());
        $itemStr = implode('', $itemLetters->toArray());

        return similar_text($queryStr, $itemStr) / max(strlen($queryStr), strlen($itemStr), 1);
    }

    private function compareMetaphone(string $queryMetaphone, string $itemMetaphone): float
    {
        if (empty($queryMetaphone) && empty($itemMetaphone)) {
            return 1.0;
        }

        if (empty($queryMetaphone) || empty($itemMetaphone)) {
            return 0.0;
        }

        return similar_text($queryMetaphone, $itemMetaphone) / max(strlen($queryMetaphone), strlen($itemMetaphone));
    }

    private function compareWords(string $queryWord, string $itemWord): float
    {
        if (empty($queryWord) && empty($itemWord)) {
            return 1.0;
        }

        if (empty($queryWord) || empty($itemWord)) {
            return 0.0;
        }

        return similar_text($queryWord, $itemWord) / max(strlen($queryWord), strlen($itemWord));
    }

    private function compareBigrams(StringTypedCollection $queryBigrams, StringTypedCollection $itemBigrams): float
    {
        $queryArray = $queryBigrams->toArray();
        $itemArray = $itemBigrams->toArray();

        if (empty($queryArray) && empty($itemArray)) {
            return 1.0;
        }

        if (empty($queryArray) || empty($itemArray)) {
            return 0.0;
        }

        $common = array_intersect($queryArray, $itemArray);
        $total = count($queryArray) + count($itemArray);

        return $total > 0 ? (2 * count($common)) / $total : 0.0;
    }

    private function convertToItemWordsCollection(SearchIndexCollection $searchIndexRecords): ItemWordsCollection
    {
        $collection = new ItemWordsCollection;

        foreach ($searchIndexRecords as $record) {
            $itemWords = $record->item_words;
            $itemNgrams = $record->ngrams->toArray();

            foreach ($itemWords as $wordVector) {
                $word = $wordVector->word;

                if (empty($word)) {
                    continue;
                }

                $collection->add(ItemWordRecord::from([
                    'normalized' => $this->normalizer->normalize($word),
                    'ngrams' => StringTypedCollection::from($itemNgrams),
                    'max_score' => $this->queryProcessor->calculateMaxScore($word),
                    'search_index' => $record,
                ]));
            }
        }

        return $collection;
    }
}
