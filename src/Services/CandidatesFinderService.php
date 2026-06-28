<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Contracts\Services\CandidatesFinderServiceInterface;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;

final class CandidatesFinderService implements CandidatesFinderServiceInterface
{
    public function __construct(
        private readonly SearchIndexRepository $repository,
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
        private readonly QueryProcessorService $queryProcessor,
    ) {}

    public function findCandidates(SearchQueryRecord $query): ItemWordsCollection
    {
        $queryText = $query->query->getValue();
        $normalized = $this->normalizer->normalize($queryText);

        $words = Sequential::from(explode(' ', $normalized));
        $ngrams = Sequential::from($this->ngramService->generateFromText($queryText)->toArray());

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => $query->searchable_type,
            'searchable_id' => $query->searchable_id,
            'source_column' => $query->source_column,
        ]);

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, $query->limit ?? 100);

        $indexes = $this->repository->findCandidates($candidatesVO);

        $collection = new ItemWordsCollection;

        foreach ($indexes as $index) {
            $itemWords = $index->getItemWords()->toArray();
            $itemNgrams = $index->getNgrams()->toArray();
            $searchIndexRecord = $index->toRecord();

            foreach ($itemWords as $word) {
                $collection->add(ItemWordRecord::from([
                    'normalized' => $this->normalizer->normalize($word),
                    'ngrams' => Sequential::from($itemNgrams),
                    'max_score' => $this->queryProcessor->calculateMaxScore($word),
                    'search_index' => $searchIndexRecord,
                ]));
            }
        }

        return $collection;
    }
}
