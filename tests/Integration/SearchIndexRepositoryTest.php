<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration;

use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\LaravelSearch\ValueObjects\ItemWordsVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use AndyDefer\Repository\Records\FindByRecord;

final class SearchIndexRepositoryTest extends IntegrationTestCase
{
    private SearchIndexRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SearchIndexRepository;
    }

    // ============================================================
    // TESTS DE CRÉATION
    // ============================================================

    public function test_create(): void
    {
        $record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from('App\Models\User'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('name'),
            original_text: StringVO::from('John Doe'),
            item_words: new ItemWordsVO('John Doe'),
            ngrams: new NgramsVO('John Doe'),
        );

        $index = $this->repository->create($record);

        $this->assertInstanceOf(SearchIndex::class, $index);
        $this->assertNotNull($index->id);
        $this->assertSame('App\Models\User', $index->searchable_type);
        $this->assertSame('1', $index->searchable_id);
        $this->assertSame('name', $index->source_column);
        $this->assertSame('John Doe', $index->original_text);
        $this->assertSame(['john', 'doe'], $index->item_words);
        $this->assertNotEmpty($index->ngrams);
    }

    public function test_create_with_specific_uuid(): void
    {
        $uuid = UuidVO::from('550e8400-e29b-41d4-a716-446655440000');

        $record = new SearchIndexRecord(
            id: $uuid,
            searchable_type: StringVO::from('App\Models\User'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('email'),
            original_text: StringVO::from('john@example.com'),
            item_words: new ItemWordsVO('john@example.com'),
            ngrams: new NgramsVO('john@example.com'),
        );

        $index = $this->repository->create($record);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $index->id);
    }

    // ============================================================
    // TESTS DE RECHERCHE AVEC SCORE
    // ============================================================

    public function test_search_with_score_exact_match(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Bob Johnson', 'name');

        $results = $this->repository->searchWithScore('John Doe', 10);

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(SearchResultRecord::class, $results[0]);
        $this->assertSame('John Doe', $results[0]->index->original_text->getValue());
        $this->assertEquals(100.0, $results[0]->percentage);
    }

    public function test_search_with_score_partial_match(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('John Johnson', 'name');

        $results = $this->repository->searchWithScore('John', 10);

        $this->assertCount(2, $results);
        $this->assertGreaterThan(0, $results[0]->percentage);
        $this->assertGreaterThan(0, $results[1]->percentage);
    }

    public function test_search_with_score_no_match(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $results = $this->repository->searchWithScore('XYZ', 10);

        $this->assertEmpty($results);
    }

    public function test_search_with_score_and_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createIndex("User {$i}", 'name');
        }

        $results = $this->repository->searchWithScoreAndLimit('User', 5, 0);

        $this->assertCount(5, $results);
        $this->assertGreaterThan(0, $results[0]->percentage);
    }

    public function test_search_with_score_and_min_percentage(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Bob Johnson', 'name');

        $results = $this->repository->searchWithScoreAndLimit('John', 10, 50);

        // Seulement les résultats avec > 50% de pertinence
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(50, $result->percentage);
        }
    }

    public function test_search_with_score_sorts_by_relevance(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('John Johnson', 'name');
        $this->createIndex('Jane Smith', 'name');

        $results = $this->repository->searchWithScore('John', 10);

        // Le premier résultat doit être le plus pertinent
        if (count($results) > 1) {
            $this->assertGreaterThanOrEqual($results[1]->percentage, $results[0]->percentage);
        }
    }

    public function test_search_with_score_and_limit_returns_record(): void
    {
        $this->createIndex('John Doe', 'name');

        $results = $this->repository->searchWithScoreAndLimit('John Doe', 10, 0);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(SearchResultRecord::class, $results[0]);
        $this->assertInstanceOf(SearchIndexRecord::class, $results[0]->index);
        $this->assertSame('John Doe', $results[0]->index->original_text->getValue());
        $this->assertEquals(100.0, $results[0]->percentage);
    }

    public function test_search_with_score_multiple_words(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Doe', 'name');
        $this->createIndex('John Smith', 'name');

        $results = $this->repository->searchWithScore('John Doe', 10);

        $this->assertCount(2, $results);
        // John Doe doit être le premier (100%)
        $this->assertSame('John Doe', $results[0]->index->original_text->getValue());
        $this->assertEquals(100.0, $results[0]->percentage);
    }

    public function test_search_with_score_with_accents(): void
    {
        $this->createIndex('Jean-Pierre', 'name');
        $this->createIndex('Jean', 'name');

        $results = $this->repository->searchWithScore('Jean', 10);

        $this->assertCount(2, $results);
        // Jean doit avoir un score plus élevé que Jean-Pierre
        $this->assertSame('Jean', $results[0]->index->original_text->getValue());
        $this->assertGreaterThan($results[1]->percentage, $results[0]->percentage);
    }

    // ============================================================
    // TESTS DE RECHERCHE EXISTANTS
    // ============================================================

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

    public function test_find_by_word_case_insensitive(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('JOHN SMITH', 'name');

        $word = StringVO::from('john');
        $results = $this->repository->findByWord($word);

        $this->assertCount(2, $results);
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

    public function test_find_by_ngrams_vo(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngramsVO = new NgramsVO('John');
        $results = $this->repository->findByNgramsVO($ngramsVO);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_word_for_ngrams(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $word = StringVO::from('John');
        $results = $this->repository->findByWordForNgrams($word);

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

    public function test_find_by_word_with_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('John Johnson', 'name');

        $word = StringVO::from('john');
        $results = $this->repository->findByWordWithSort(
            $word,
            'original_text:asc',
            10,
            ['id', 'original_text']
        );

        $this->assertCount(2, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('John Johnson', $results->last()->original_text);
    }

    public function test_find_by_source_with_sort(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('Alice Brown', 'name', 'App\Models\User', '3');

        $sourceType = StringVO::from('App\Models\User');
        $results = $this->repository->findBySourceWithSort(
            $sourceType,
            null,
            'original_text:desc',
            10
        );

        $this->assertCount(3, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('Alice Brown', $results->last()->original_text);
    }

    public function test_find_with_multiple_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('John Johnson', 'name');

        $word = StringVO::from('john');
        $results = $this->repository->findByWithMultipleSort(
            $word,
            'original_text:asc|created_at:desc',
            10,
            ['id', 'original_text', 'created_at']
        );

        $this->assertCount(2, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_by_with_filters(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');
        $this->createIndex('John Doe', 'name', 'App\Models\Product', '1');

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from('App\Models\User'),
            original_text: StringVO::from('John')
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
        $this->assertSame('App\Models\User', $results->first()->searchable_type);
    }

    public function test_find_by_with_ngrams_filter(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $ngramsVO = new NgramsVO('John');
        $filters = new SearchIndexFiltersRecord(
            ngrams: $ngramsVO
        );

        $findBy = new FindByRecord(
            filters: $filters
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->original_text);
    }

    public function test_find_all_with_sort(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Alice Brown', 'name');

        $results = $this->repository->findAllWithSort('original_text:asc', 10);

        $this->assertCount(3, $results);
        $this->assertSame('Alice Brown', $results->first()->original_text);
        $this->assertSame('John Doe', $results->last()->original_text);
    }

    // ============================================================
    // TESTS DE SUPPRESSION
    // ============================================================

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

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from('App\Models\User')
        );

        $count = $this->repository->deleteBulk($filters);

        $this->assertSame(2, $count);

        $remaining = $this->repository->findAllWithSort('created_at:asc', 10);
        $this->assertCount(1, $remaining);
        $this->assertSame('Product A', $remaining->first()->original_text);
    }

    // ============================================================
    // TESTS DES GETTERS
    // ============================================================

    public function test_model_getters_return_vo(): void
    {
        $index = $this->createIndex('John Doe', 'name');

        $this->assertInstanceOf(UuidVO::class, $index->getId());
        $this->assertInstanceOf(StringVO::class, $index->getSearchableType());
        $this->assertInstanceOf(StringVO::class, $index->getSearchableId());
        $this->assertInstanceOf(StringVO::class, $index->getSourceColumn());
        $this->assertInstanceOf(StringVO::class, $index->getOriginalText());
        $this->assertInstanceOf(ItemWordsVO::class, $index->getItemWords());
        $this->assertInstanceOf(NgramsVO::class, $index->getNgrams());
    }

    public function test_to_record(): void
    {
        $index = $this->createIndex('John Doe', 'name');

        $record = $index->toRecord();

        $this->assertInstanceOf(SearchIndexRecord::class, $record);
        $this->assertSame($index->id, $record->id->getValue());
        $this->assertSame($index->searchable_type, $record->searchable_type->getValue());
        $this->assertSame($index->searchable_id, $record->searchable_id->getValue());
        $this->assertSame($index->source_column, $record->source_column->getValue());
        $this->assertSame($index->original_text, $record->original_text->getValue());
    }

    public function test_create_with_ngrams_vo(): void
    {
        $ngramsVO = new NgramsVO('Terminal');

        $record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from('App\Models\Product'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('name'),
            original_text: StringVO::from('Terminal'),
            item_words: new ItemWordsVO('Terminal'),
            ngrams: $ngramsVO,
        );

        $index = $this->repository->create($record);

        $this->assertInstanceOf(SearchIndex::class, $index);
        $this->assertEquals($ngramsVO->toArray(), $index->ngrams);
    }

    public function test_find_by_ngrams_vo_with_multiple_words(): void
    {
        $this->createIndex('Terminal App', 'name');
        $this->createIndex('Terminal Pro', 'name');
        $this->createIndex('App Terminal', 'name');

        $ngramsVO = new NgramsVO('Terminal');
        $results = $this->repository->findByNgramsVO($ngramsVO);

        $this->assertCount(3, $results);
    }

    public function test_find_by_word_for_ngrams_with_partial_match(): void
    {
        $this->createIndex('Terminal Application', 'name');
        $this->createIndex('Terminal Pro', 'name');
        $this->createIndex('Application', 'name');

        $word = StringVO::from('Terminal');
        $results = $this->repository->findByWordForNgrams($word);

        $this->assertCount(2, $results);
    }

    // ============================================================
    // MÉTHODES UTILITAIRES
    // ============================================================

    private function createIndex(
        string $text,
        string $column = 'name',
        string $type = 'App\Models\User',
        string $id = '1'
    ): SearchIndex {
        $record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from($type),
            searchable_id: StringVO::from($id),
            source_column: StringVO::from($column),
            original_text: StringVO::from($text),
            item_words: new ItemWordsVO($text),
            ngrams: new NgramsVO($text),
        );

        return $this->repository->create($record);
    }
}
