<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Support\Collection;

final class SearchIndexRepositoryTest extends IntegrationTestCase
{
    private SearchIndexRepository $repository;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->repository = new SearchIndexRepository($this->ngramService);
    }

    public function test_create(): void
    {
        $words = Sequential::from(['john', 'doe']);
        $ngrams = Sequential::from($this->ngramService->generateFromText('John Doe')->toArray());

        $record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from('App\Models\User'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('name'),
            original_text: StringVO::from('John Doe'),
            normalized_text: StringVO::from($this->normalizer->normalize('John Doe')),
            item_words: $words,
            ngrams: $ngrams,
        );

        $index = $this->repository->create($record);

        $this->assertInstanceOf(SearchIndex::class, $index);
        $this->assertNotNull($index->id);
        $this->assertSame('App\Models\User', $index->searchable_type);
        $this->assertSame('1', $index->searchable_id);
        $this->assertSame('name', $index->source_column);
        $this->assertSame('John Doe', $index->original_text);
        $this->assertSame('john doe', $index->normalized_text);
        $this->assertSame(['john', 'doe'], $index->item_words);
        $this->assertNotEmpty($index->ngrams);
    }

    public function test_find_by_word(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Bob Johnson', 'name');

        $word = StringVO::from('john');
        $results = $this->repository->findByWord($word);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_word_with_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('John Johnson', 'name');

        $word = StringVO::from('john');
        $sort = new SortColumns('original_text:asc');
        $results = $this->repository->findByWordWithSort($word, $sort, 10);

        $this->assertCount(2, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('John Johnson', $results->last()->original_text);
    }

    public function test_find_by_ngram(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngram = StringVO::from('joh');
        $results = $this->repository->findByNgram($ngram);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_ngram_with_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngram = StringVO::from('joh');
        $sort = new SortColumns('original_text:asc');
        $results = $this->repository->findByNgramWithSort($ngram, $sort, 10);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_source(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $sourceType = StringVO::from('App\Models\User');
        $results = $this->repository->findBySource($sourceType);

        $this->assertCount(2, $results);
    }

    public function test_find_by_source_with_id(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');

        $sourceType = StringVO::from('App\Models\User');
        $sourceId = StringVO::from('1');
        $results = $this->repository->findBySource($sourceType, $sourceId);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_source_with_sort(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Alice Brown', 'name', 'App\Models\User', '3');

        $sourceType = StringVO::from('App\Models\User');
        $sort = new SortColumns('original_text:desc');
        $results = $this->repository->findBySourceWithSort($sourceType, $sort, null, 10);

        $this->assertCount(3, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('Alice Brown', $results->last()->original_text);
    }

    public function test_find_by_text(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $text = StringVO::from('John');
        $results = $this->repository->findByText($text);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_word_and_source(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('John Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Product', 'name', 'App\Models\Product', '1');

        $word = StringVO::from('john');
        $sourceType = StringVO::from('App\Models\User');
        $results = $this->repository->findByWordAndSource($word, $sourceType);

        $this->assertCount(2, $results);
    }

    public function test_find_all_with_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Alice Brown', 'name');

        $sort = new SortColumns('original_text:asc');
        $results = $this->repository->findAllWithSort($sort, 10);

        $this->assertCount(3, $results);
        $this->assertSame('Alice Brown', $results->first()->original_text);
        $this->assertSame('John Doe', $results->last()->original_text);
    }

    public function test_find_candidates_with_search_candidates_vo(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Bob Thompson', 'name');

        $normalized = $this->normalizer->normalize('John');
        $words = Sequential::from(explode(' ', $normalized));
        $ngrams = Sequential::from($this->ngramService->generateFromText('John')->toArray());
        $filters = new SearchIndexFiltersRecord;

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, 10);

        $results = $this->repository->findCandidates($candidatesVO);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_candidates_with_filters(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Doe', 'name', 'App\Models\Product', '1');

        $normalized = $this->normalizer->normalize('John');
        $words = Sequential::from(explode(' ', $normalized));
        $ngrams = Sequential::from($this->ngramService->generateFromText('John')->toArray());
        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from('App\Models\User'),
        ]);

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, 10);

        $results = $this->repository->findCandidates($candidatesVO);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('App\Models\User', $results->first()->searchable_type);
    }

    public function test_find_candidates_empty_ngrams_and_words(): void
    {
        $this->createIndex('John Doe', 'name');

        $candidatesVO = SearchCandidatesVO::empty(10);

        $results = $this->repository->findCandidates($candidatesVO);

        $this->assertCount(0, $results);
    }

    // ============================================================
    // TESTS POUR countByFilters
    // ============================================================

    public function test_count_by_filters_with_searchable_type(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from('App\Models\User'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(2, $count);
    }

    public function test_count_by_filters_with_searchable_id(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');

        $filters = new SearchIndexFiltersRecord(
            searchable_id: StringVO::from('1'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_source_column(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'email', 'App\Models\User', '2');

        $filters = new SearchIndexFiltersRecord(
            source_column: StringVO::from('name'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_original_text(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = new SearchIndexFiltersRecord(
            original_text: StringVO::from('John'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_normalized_text(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = new SearchIndexFiltersRecord(
            normalized_text: StringVO::from('john'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_multiple_filters(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('John Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Product', 'name', 'App\Models\Product', '1');

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from('App\Models\User'),
            original_text: StringVO::from('John'),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(2, $count);
    }

    public function test_count_by_filters_with_no_filters(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = new SearchIndexFiltersRecord;

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(2, $count);
    }

    public function test_count_by_filters_with_item_words(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = new SearchIndexFiltersRecord(
            item_words: Sequential::from(['john']),
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_ngrams(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngrams = Sequential::from($this->ngramService->generate('john')->toArray());

        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngrams,
        );

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_empty_filters(): void
    {
        $this->createIndex('John Doe', 'name');

        $filters = new SearchIndexFiltersRecord;

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    // ============================================================
    // TESTS POUR countDistinctEntities
    // ============================================================

    public function test_count_distinct_entities(): void
    {
        // Créer plusieurs indexes pour le même utilisateur (plusieurs colonnes)
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');
        $this->createIndex('Software Developer', 'description', 'App\Models\User', '1');

        // Créer un autre utilisateur
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('jane@example.com', 'email', 'App\Models\User', '2');

        $count = $this->repository->countDistinctEntities('App\Models\User');

        // 2 utilisateurs distincts (id: 1 et 2)
        $this->assertEquals(2, $count);
    }

    public function test_count_distinct_entities_with_no_indexes(): void
    {
        $count = $this->repository->countDistinctEntities('App\Models\User');

        $this->assertEquals(0, $count);
    }

    public function test_count_distinct_entities_with_multiple_types(): void
    {
        // Créer des indexes pour User
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');

        // Créer des indexes pour Product
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');
        $this->createIndex('REF001', 'reference', 'App\Models\Product', '1');

        $userCount = $this->repository->countDistinctEntities('App\Models\User');
        $productCount = $this->repository->countDistinctEntities('App\Models\Product');

        $this->assertEquals(1, $userCount);
        $this->assertEquals(1, $productCount);
    }

    public function test_count_distinct_entities_with_same_entity_multiple_indexes(): void
    {
        // Créer 5 indexes pour le même utilisateur (5 colonnes différentes)
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');
        $this->createIndex('Software Developer', 'description', 'App\Models\User', '1');
        $this->createIndex('Paris', 'city', 'App\Models\User', '1');
        $this->createIndex('France', 'country', 'App\Models\User', '1');

        $count = $this->repository->countDistinctEntities('App\Models\User');

        // 1 utilisateur unique malgré 5 indexes
        $this->assertEquals(1, $count);
    }

    public function test_count_distinct_entities_with_different_entities(): void
    {
        // Créer des indexes pour plusieurs utilisateurs
        for ($i = 1; $i <= 5; $i++) {
            $this->createIndex("User {$i}", 'name', 'App\Models\User', (string) $i);
            $this->createIndex("user{$i}@example.com", 'email', 'App\Models\User', (string) $i);
        }

        $count = $this->repository->countDistinctEntities('App\Models\User');

        // 5 utilisateurs distincts
        $this->assertEquals(5, $count);
    }

    public function test_delete(): void
    {
        $index = $this->createIndex('John Doe', 'name');

        $deleted = $this->repository->delete($index->id);

        $this->assertTrue($deleted);
        $this->assertNull($this->repository->find($index->id));
    }

    public function test_delete_bulk(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from('App\Models\User'),
        ]);

        $count = $this->repository->deleteBulk($filters);

        $this->assertSame(2, $count);

        $remaining = $this->repository->findAllWithSort(new SortColumns('created_at:asc'), 10);
        $this->assertCount(1, $remaining);
        $this->assertSame('Product A', $remaining->first()->original_text);
    }

    private function createIndex(
        string $text,
        string $column = 'name',
        string $type = 'App\Models\User',
        string $id = '1'
    ): SearchIndex {
        $words = Sequential::from(explode(' ', strtolower($text)));
        $ngrams = Sequential::from($this->ngramService->generateFromText($text)->toArray());

        $record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from($type),
            searchable_id: StringVO::from($id),
            source_column: StringVO::from($column),
            original_text: StringVO::from($text),
            normalized_text: StringVO::from($this->normalizer->normalize($text)),
            item_words: $words,
            ngrams: $ngrams,
        );

        return $this->repository->create($record);
    }
}
