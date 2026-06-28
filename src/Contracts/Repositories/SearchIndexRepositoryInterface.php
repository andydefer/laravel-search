<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Repositories;

use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Support\Collection;

interface SearchIndexRepositoryInterface extends AbstractRepositoryInterface
{
    public function findByWord(StringVO $word): Collection;

    public function findByWordWithSort(
        StringVO $word,
        SortColumns $sort,
        int $limit = 10,
        ?SelectColumns $columns = null
    ): Collection;

    public function findByNgram(StringVO $ngram): Collection;

    public function findByNgramWithSort(
        StringVO $ngram,
        SortColumns $sort,
        int $limit = 10
    ): Collection;

    public function findByWordForNgrams(StringVO $word): Collection;

    public function findBySource(StringVO $sourceType, ?StringVO $sourceId = null): Collection;

    public function findBySourceWithSort(
        StringVO $sourceType,
        SortColumns $sort,
        ?StringVO $sourceId = null,
        int $limit = 10
    ): Collection;

    public function findByText(StringVO $text): Collection;

    public function findByWordAndSource(
        StringVO $word,
        StringVO $sourceType,
        ?StringVO $sourceId = null
    ): Collection;

    public function findByWithMultipleSort(
        StringVO $word,
        SortColumns $sort,
        int $limit = 20,
        ?SelectColumns $columns = null
    ): Collection;

    public function findAllWithSort(SortColumns $sort, int $limit = 100): Collection;

    public function countByFilters(SearchIndexFiltersRecord $filters): int;

    public function countDistinctEntities(string $morphClass): int;

    /**
     * Retourne une collection de SearchIndexRecord (candidats)
     */
    public function findCandidatesBySimilarity(
        SearchCandidatesVO $candidates,
        WordVectorCollection $queryWordVectors,
        int $minCommonBigrams = 2
    ): SearchIndexCollection;
}
