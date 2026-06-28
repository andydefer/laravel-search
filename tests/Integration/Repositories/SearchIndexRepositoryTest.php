<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\WordVectorRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\ValueObjects\SortColumns;

final class SearchIndexRepositoryTest extends IntegrationTestCase
{
    private SearchIndexRepository $repository;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    private SearchConfig $config;

    private WordVectorParserService $wordVectorParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->wordVectorParser = $this->app->make(WordVectorParserService::class);
        $this->config = $this->app->make(SearchConfig::class);
        $this->repository = new SearchIndexRepository($this->ngramService, $this->wordVectorParser, $this->config);
    }

    public function test_create(): void
    {
        $words = $this->wordVectorParser->parse(explode(' ', strtolower('John Doe')));
        $ngrams = $this->ngramService->generateFromText('John Doe')->toArray();

        $record = SearchIndexRecord::from([
            'id' => UuidVO::generate(),
            'searchable_type' => 'App\Models\User',
            'searchable_id' => '1',
            'source_column' => 'name',
            'original_text' => 'John Doe',
            'normalized_text' => $this->normalizer->normalize('John Doe'),
            'item_words' => $words,
            'ngrams' => $ngrams,
        ]);

        $index = $this->repository->create($record);

        $this->assertInstanceOf(SearchIndex::class, $index);
        $this->assertNotNull($index->getId());
        $this->assertSame('App\Models\User', $index->getSearchableType()->getValue());
        $this->assertSame('1', $index->getSearchableId()->getValue());
        $this->assertSame('name', $index->getSourceColumn()->getValue());
        $this->assertSame('John Doe', $index->getOriginalText()->getValue());
        $this->assertSame('john doe', $index->getNormalizedText()->getValue());
        $this->assertNotEmpty($index->getItemWords());
        $this->assertNotEmpty($index->getNgrams());
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

    public function test_find_by_with_multiple_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('John Johnson', 'name');

        $word = StringVO::from('john');
        $sort = new SortColumns('original_text:asc|created_at:desc');
        $results = $this->repository->findByWithMultipleSort($word, $sort, 10);

        $this->assertCount(2, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
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

    public function test_count_by_filters_with_searchable_type(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => 'App\Models\User',
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(2, $count);
    }

    public function test_count_by_filters_with_searchable_id(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');

        $filters = SearchIndexFiltersRecord::from([
            'searchable_id' => '1',
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_source_column(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'email', 'App\Models\User', '2');

        $filters = SearchIndexFiltersRecord::from([
            'source_column' => 'name',
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_original_text(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = SearchIndexFiltersRecord::from([
            'original_text' => 'John',
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_normalized_text(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $filters = SearchIndexFiltersRecord::from([
            'normalized_text' => 'john',
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_multiple_filters(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('John Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Product', 'name', 'App\Models\Product', '1');

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => 'App\Models\User',
            'original_text' => 'John',
        ]);

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

        $uris = $this->wordVectorParser->parse(['john']);

        $filters = SearchIndexFiltersRecord::from([
            'item_words' => $uris,
        ]);

        $count = $this->repository->countByFilters($filters);

        $this->assertEquals(1, $count);
    }

    public function test_count_by_filters_with_ngrams(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngrams = $this->ngramService->generate('john')->toArray();

        $filters = SearchIndexFiltersRecord::from([
            'ngrams' => $ngrams,
        ]);

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

    public function test_count_distinct_entities(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');
        $this->createIndex('Software Developer', 'description', 'App\Models\User', '1');

        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('jane@example.com', 'email', 'App\Models\User', '2');

        $count = $this->repository->countDistinctEntities('App\Models\User');

        $this->assertEquals(2, $count);
    }

    public function test_count_distinct_entities_with_no_indexes(): void
    {
        $count = $this->repository->countDistinctEntities('App\Models\User');

        $this->assertEquals(0, $count);
    }

    public function test_count_distinct_entities_with_multiple_types(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');

        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');
        $this->createIndex('REF001', 'reference', 'App\Models\Product', '1');

        $userCount = $this->repository->countDistinctEntities('App\Models\User');
        $productCount = $this->repository->countDistinctEntities('App\Models\Product');

        $this->assertEquals(1, $userCount);
        $this->assertEquals(1, $productCount);
    }

    public function test_count_distinct_entities_with_same_entity_multiple_indexes(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('john@example.com', 'email', 'App\Models\User', '1');
        $this->createIndex('Software Developer', 'description', 'App\Models\User', '1');
        $this->createIndex('Paris', 'city', 'App\Models\User', '1');
        $this->createIndex('France', 'country', 'App\Models\User', '1');

        $count = $this->repository->countDistinctEntities('App\Models\User');

        $this->assertEquals(1, $count);
    }

    public function test_count_distinct_entities_with_different_entities(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createIndex("User {$i}", 'name', 'App\Models\User', (string) $i);
            $this->createIndex("user{$i}@example.com", 'email', 'App\Models\User', (string) $i);
        }

        $count = $this->repository->countDistinctEntities('App\Models\User');

        $this->assertEquals(5, $count);
    }

    // ============================================================
    // TESTS SUPPRIMÉS : findCandidates n'existe plus
    // ============================================================

    public function test_find_candidates_by_similarity(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Thomas Monroe', 'name');
        $this->createIndex('Bob Johnson', 'name');

        $queryWords = $this->wordVectorParser->parse(['john']);
        $normalized = $this->normalizer->normalize('John');
        $words = explode(' ', $normalized);
        $ngrams = $this->ngramService->generateFromText('John')->toArray();
        $filters = new SearchIndexFiltersRecord;

        $candidatesVO = new SearchCandidatesVO(
            words: StringTypedCollection::from($words),
            ngrams: StringTypedCollection::from($ngrams),
            filters: $filters,
        );

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $queryWords, 2);

        $this->assertCount(2, $results);

        $texts = [];
        foreach ($results as $record) {
            $texts[] = $record->original_text->getValue();
        }

        $this->assertContains('John Doe', $texts);
        $this->assertContains('Bob Johnson', $texts);
    }

    public function test_find_candidates_by_similarity_with_filters(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Doe', 'name', 'App\Models\Product', '1');

        $queryWords = $this->wordVectorParser->parse(['john']);
        $normalized = $this->normalizer->normalize('John');
        $words = explode(' ', $normalized);
        $ngrams = $this->ngramService->generateFromText('John')->toArray();
        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => 'App\Models\User',
        ]);

        $candidatesVO = new SearchCandidatesVO(
            words: StringTypedCollection::from($words),
            ngrams: StringTypedCollection::from($ngrams),
            filters: $filters,
        );

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $queryWords, 2);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text->getValue());
        $this->assertSame('App\Models\User', $results->first()->searchable_type->getValue());
    }

    public function test_find_candidates_by_similarity_with_no_match(): void
    {
        $this->createIndex('John Doe', 'name');

        $queryWords = $this->wordVectorParser->parse(['xyz']);
        $normalized = $this->normalizer->normalize('xyz');
        $words = explode(' ', $normalized);
        $ngrams = $this->ngramService->generateFromText('xyz')->toArray();
        $filters = new SearchIndexFiltersRecord;

        $candidatesVO = new SearchCandidatesVO(
            words: StringTypedCollection::from($words),
            ngrams: StringTypedCollection::from($ngrams),
            filters: $filters,
        );

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $queryWords, 2);

        $this->assertCount(0, $results);
    }

    public function test_find_candidates_by_similarity_with_limit(): void
    {
        for ($i = 0; $i < 150; $i++) {
            $this->createIndex("User {$i}", 'name');
        }

        $queryWords = $this->wordVectorParser->parse(['user']);
        $normalized = $this->normalizer->normalize('User');
        $words = explode(' ', $normalized);
        $ngrams = $this->ngramService->generateFromText('User')->toArray();
        $filters = new SearchIndexFiltersRecord;

        $candidatesVO = new SearchCandidatesVO(
            words: StringTypedCollection::from($words),
            ngrams: StringTypedCollection::from($ngrams),
            filters: $filters,
        );

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $queryWords, 2);

        // La limite est définie par SearchConfig::getMaxCandidates() (200 par défaut)
        $this->assertLessThanOrEqual(200, $results->count());
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_word_vector_parser_parse(): void
    {
        $words = ['test', 'hello'];
        $collection = $this->wordVectorParser->parse($words);

        $this->assertInstanceOf(WordVectorCollection::class, $collection);
        $this->assertEquals(2, $collection->count());

        foreach ($collection as $record) {
            $this->assertInstanceOf(WordVectorRecord::class, $record);
            $this->assertNotEmpty($record->word);
            $this->assertNotEmpty($record->metaphone);
            $this->assertNotEmpty($record->bigrams->toArray());
            $this->assertNotEmpty($record->metaphone_bigrams->toArray());
        }
    }

    public function test_word_vector_parser_unparse(): void
    {
        $words = ['test'];
        $collection = $this->wordVectorParser->parse($words);
        $uris = $this->wordVectorParser->unparse($collection);

        $this->assertInstanceOf(StringTypedCollection::class, $uris);
        $this->assertEquals(1, $uris->count());

        $uri = $uris->first();
        $this->assertStringContainsString('test?', $uri);
        $this->assertStringContainsString('metaphone=', $uri);
        $this->assertStringContainsString('unique_letters', $uri);
        $this->assertStringContainsString('bigrams', $uri);
        $this->assertStringContainsString('metaphone_bigrams', $uri);
    }

    public function test_word_vector_parser_empty_array(): void
    {
        $collection = $this->wordVectorParser->parse([]);
        $this->assertInstanceOf(WordVectorCollection::class, $collection);
        $this->assertEquals(0, $collection->count());

        $uris = $this->wordVectorParser->unparse($collection);
        $this->assertInstanceOf(StringTypedCollection::class, $uris);
        $this->assertEquals(0, $uris->count());
    }

    public function test_delete(): void
    {
        $index = $this->createIndex('John Doe', 'name');

        $deleted = $this->repository->delete($index->getId()->getValue());

        $this->assertTrue($deleted);
        $this->assertNull($this->repository->find($index->getId()->getValue()));
    }

    public function test_delete_bulk(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => 'App\Models\User',
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
        $words = $this->wordVectorParser->parse(explode(' ', strtolower($text)));
        $ngrams = $this->ngramService->generateFromText($text)->toArray();

        $record = SearchIndexRecord::from([
            'id' => UuidVO::generate(),
            'searchable_type' => $type,
            'searchable_id' => $id,
            'source_column' => $column,
            'original_text' => $text,
            'normalized_text' => $this->normalizer->normalize($text),
            'item_words' => $words,
            'ngrams' => $ngrams,
        ]);

        return $this->repository->create($record);
    }
}
