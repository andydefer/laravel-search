<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Unit\Services;

use AndyDefer\LaravelSearch\Records\ProcessedWordRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchResultRecord;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\ValueObjects\ItemWordsVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use PHPUnit\Framework\TestCase;

final class QueryProcessorServiceTest extends TestCase
{
    private QueryProcessorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QueryProcessorService(new TextNormalizerService);
    }

    public function test_process_query(): void
    {
        $result = $this->service->process('Hello World');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ProcessedWordRecord::class, $result[0]);
        $this->assertSame('hello', $result[0]->normalized);
        $this->assertSame('world', $result[1]->normalized);
        $this->assertNotEmpty($result[0]->ngrams->toArray());
    }

    public function test_process_query_with_accents(): void
    {
        $result = $this->service->process('Café thé');

        $this->assertCount(2, $result);
        $this->assertSame('cafe', $result[0]->normalized);
        $this->assertSame('the', $result[1]->normalized);
    }

    public function test_process_empty_query(): void
    {
        $result = $this->service->process('');
        $this->assertEmpty($result);
    }

    public function test_compute_score_exact_match(): void
    {
        $query_words = $this->service->process('hello');
        $item_words = ['hello', 'world'];

        $score = $this->service->compute_score($query_words, $item_words);

        $this->assertNotNull($score);
        $this->assertEquals(1.0, $score->score);
        $this->assertEquals(100.0, $score->percentage);
    }

    public function test_compute_score_partial_match(): void
    {
        $query_words = $this->service->process('hello');
        $item_words = ['world', 'testing', 'hel'];

        $score = $this->service->compute_score($query_words, $item_words);

        $this->assertNotNull($score);
        $this->assertGreaterThan(0, $score->percentage);
    }

    public function test_compute_score_no_match(): void
    {
        $query_words = $this->service->process('hello');
        $item_words = ['xyz', 'abc'];

        $score = $this->service->compute_score($query_words, $item_words);

        $this->assertNull($score);
    }

    public function test_find_best_match_exact(): void
    {
        $query_word = $this->service->process('hello')[0];
        $item_words = ['hello', 'world', 'test'];

        $result = $this->service->find_best_match($query_word, $item_words);

        $this->assertEquals(1.0, $result->score);
        $this->assertEquals(100.0, $result->percentage);
    }

    public function test_find_best_match_partial(): void
    {
        $query_word = $this->service->process('hello')[0];
        $item_words = ['hell', 'world', 'test'];

        $result = $this->service->find_best_match($query_word, $item_words);

        $this->assertGreaterThan(0, $result->score);
        $this->assertLessThan(100, $result->percentage);
    }

    public function test_sort_results(): void
    {
        $index_record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from('test'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('name'),
            original_text: StringVO::from('Test'),
            item_words: ItemWordsVO::fromArray(['test']),
            ngrams: new NgramsVO('test'),
        );

        $results = [
            new SearchResultRecord(
                index: $index_record,
                score: 0.5,
                max_possible: 1.0,
                percentage: 50.0
            ),
            new SearchResultRecord(
                index: $index_record,
                score: 0.8,
                max_possible: 1.0,
                percentage: 80.0
            ),
            new SearchResultRecord(
                index: $index_record,
                score: 0.3,
                max_possible: 1.0,
                percentage: 30.0
            ),
        ];

        $sorted = $this->service->sort_results($results);

        $this->assertSame(80.0, $sorted[0]->percentage);
        $this->assertSame(50.0, $sorted[1]->percentage);
        $this->assertSame(30.0, $sorted[2]->percentage);
    }

    public function test_sort_results_with_equal_percentage(): void
    {
        $index_record = new SearchIndexRecord(
            id: UuidVO::generate(),
            searchable_type: StringVO::from('test'),
            searchable_id: StringVO::from('1'),
            source_column: StringVO::from('name'),
            original_text: StringVO::from('Test'),
            item_words: ItemWordsVO::fromArray(['test']),
            ngrams: new NgramsVO('test'),
        );

        $results = [
            new SearchResultRecord(
                index: $index_record,
                score: 0.3,
                max_possible: 1.0,
                percentage: 50.0
            ),
            new SearchResultRecord(
                index: $index_record,
                score: 0.8,
                max_possible: 1.0,
                percentage: 50.0
            ),
        ];

        $sorted = $this->service->sort_results($results);

        $this->assertSame(0.8, $sorted[0]->score);
        $this->assertSame(0.3, $sorted[1]->score);
    }

    public function test_compute_score_with_multiple_query_words(): void
    {
        $query_words = $this->service->process('helo world');
        $item_words = ['hello', 'testing'];

        $score = $this->service->compute_score($query_words, $item_words);

        $this->assertNotNull($score);
        $this->assertGreaterThan(0, $score->score);
        $this->assertLessThan(100, $score->percentage);
    }
}
