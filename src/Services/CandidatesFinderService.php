<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Contracts\Services\CandidatesFinderServiceInterface;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use Illuminate\Support\Collection;

final class CandidatesFinderService implements CandidatesFinderServiceInterface
{
    private const MAX_CANDIDATES_BEFORE_FILTER = 100;

    private const MIN_COMMON_BIGRAMS = 2;

    private const MIN_COMMON_METAPHONE_BIGRAMS = 1;

    public function __construct(
        private readonly SearchIndexRepository $repository,
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
        private readonly QueryProcessorService $queryProcessor,
        private readonly WordVectorParserService $wordVectorParser,
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

        // Phase 1: Recherche initiale avec limite plus élevée
        $initialLimit = self::MAX_CANDIDATES_BEFORE_FILTER * 2;

        $words = StringTypedCollection::from($queryWords);
        $ngrams = StringTypedCollection::from($this->ngramService->generateFromText($queryText)->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, $initialLimit);
        $indexes = $this->repository->findCandidatesBySimilarity(
            $candidatesVO,
            $queryWordVectors,
            self::MIN_COMMON_BIGRAMS,
            self::MIN_COMMON_METAPHONE_BIGRAMS
        );

        // Phase 2: Filtrage intelligent par similarité
        $filteredIndexes = $this->filterBySimilarity($indexes, $queryWordVectors);

        // Phase 3: Si encore trop de candidats, échantillonnage aléatoire
        if ($filteredIndexes->count() > self::MAX_CANDIDATES_BEFORE_FILTER) {
            $filteredIndexes = $this->randomSample($filteredIndexes, self::MAX_CANDIDATES_BEFORE_FILTER);
        }

        // Phase 4: Convertir en ItemWordsCollection
        return $this->convertToItemWordsCollection($filteredIndexes);
    }

    private function filterBySimilarity(Collection $indexes, WordVectorCollection $queryVectors): Collection
    {
        $filtered = [];

        foreach ($indexes as $index) {
            $itemVectors = $index->getItemWords();
            $score = $this->calculateSimilarityScore($itemVectors, $queryVectors);

            if ($score >= self::MIN_COMMON_BIGRAMS) {
                $filtered[] = $index;
            }
        }

        return new Collection($filtered);
    }

    private function calculateSimilarityScore(WordVectorCollection $itemVectors, WordVectorCollection $queryVectors): int
    {
        $totalMatches = 0;

        foreach ($queryVectors as $queryVector) {
            foreach ($itemVectors as $itemVector) {
                $commonBigrams = array_intersect($queryVector->bigrams->toArray(), $itemVector->bigrams->toArray());
                $totalMatches += count($commonBigrams);

                $commonMetaphone = array_intersect(
                    $queryVector->metaphoneBigrams->toArray(),
                    $itemVector->metaphoneBigrams->toArray()
                );
                $totalMatches += count($commonMetaphone) * 0.5;
            }
        }

        return (int) $totalMatches;
    }

    private function randomSample(Collection $collection, int $limit): Collection
    {
        if ($collection->count() <= $limit) {
            return $collection;
        }

        $items = $collection->toArray();
        shuffle($items);

        return new Collection(array_slice($items, 0, $limit));
    }

    private function convertToItemWordsCollection(Collection $indexes): ItemWordsCollection
    {
        $collection = new ItemWordsCollection;

        foreach ($indexes as $index) {
            $itemWords = $index->getItemWords()->getWords();
            $itemNgrams = $index->getNgrams()->toArray();
            $searchIndexRecord = $index->toRecord();

            foreach ($itemWords as $word) {
                $collection->add(ItemWordRecord::from([
                    'normalized' => $this->normalizer->normalize($word),
                    'ngrams' => StringTypedCollection::from($itemNgrams),
                    'max_score' => $this->queryProcessor->calculateMaxScore($word),
                    'search_index' => $searchIndexRecord,
                ]));
            }
        }

        return $collection;
    }
}
