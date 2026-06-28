<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\CandidatesFinderService;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class CandidatesFinderServiceTest extends IntegrationTestCase
{
    private CandidatesFinderService $service;

    private SearchIndexRepository $repository;

    private SearchConfig $config;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    private WordVectorParserService $wordVectorParser;

    private QueryProcessorService $queryProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->wordVectorParser = $this->app->make(WordVectorParserService::class);
        $this->queryProcessor = $this->app->make(QueryProcessorService::class);
        $this->config = $this->app->make(SearchConfig::class);
        $this->repository = new SearchIndexRepository($this->ngramService, $this->wordVectorParser, $this->config);

        $this->service = $this->app->make(CandidatesFinderService::class);
    }

    private function countUniqueIndexes(ItemWordsCollection $collection): int
    {
        $ids = [];
        foreach ($collection as $item) {
            if ($item->search_index !== null) {
                $ids[$item->search_index->id->getValue()] = true;
            }
        }

        return count($ids);
    }

    public function test_find_candidates_returns_empty_when_no_match(): void
    {
        $this->createIndex('John Doe', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('xyz'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertInstanceOf(ItemWordsCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_find_candidates_with_exact_match(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Smith', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John Doe'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertInstanceOf(ItemWordsCollection::class, $result);
        $this->assertEquals(1, $this->countUniqueIndexes($result));

        $first = $result->first();
        $this->assertEquals('john', $first->normalized->getValue());
        $this->assertNotNull($first->search_index);
        $this->assertEquals('John Doe', $first->search_index->original_text->getValue());
    }

    public function test_find_candidates_with_partial_match(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('John Johnson', 'name');
        $this->createIndex('Jane Smith', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertGreaterThanOrEqual(2, $this->countUniqueIndexes($result));

        $items = $result->toArray();
        $normalizedWords = array_map(fn ($item) => $item->normalized->getValue(), $items);
        $this->assertContains('john', $normalizedWords);
        $this->assertContains('johnson', $normalizedWords);
    }

    public function test_find_candidates_with_filters(): void
    {
        $this->createIndex('John User', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane User', 'name', 'App\Models\User', '2');
        $this->createIndex('Product A', 'name', 'App\Models\Product', '1');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('User'),
            'searchable_type' => StringVO::from('App\Models\User'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(2, $this->countUniqueIndexes($result));

        foreach ($result as $item) {
            $this->assertEquals('App\Models\User', $item->search_index->searchable_type->getValue());
        }
    }

    public function test_find_candidates_limits_to_max_candidates(): void
    {
        for ($i = 0; $i < 150; $i++) {
            $this->createIndex("User {$i}", 'name');
        }

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('User'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertLessThanOrEqual(100, $this->countUniqueIndexes($result));
        $this->assertGreaterThan(0, $this->countUniqueIndexes($result));
    }

    public function test_find_candidates_keeps_best_candidates(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('John Johnson', 'name');
        $this->createIndex('Jane Smith', 'name');
        $this->createIndex('Thomas Monroe', 'name');
        $this->createIndex('Bob Johnson', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
        ]);

        $result = $this->service->findCandidates($query);

        $words = [];
        foreach ($result as $item) {
            $words[] = $item->search_index->original_text->getValue();
        }

        $this->assertContains('John Doe', $words);
        $this->assertContains('John Johnson', $words);
        $this->assertContains('Bob Johnson', $words);
    }

    public function test_find_candidates_with_accents(): void
    {
        $this->createIndex('Jean-Pierre', 'name');
        $this->createIndex('Jean', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Jean'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(2, $this->countUniqueIndexes($result));
    }

    public function test_find_candidates_with_multiple_words(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('Jane Doe', 'name');
        $this->createIndex('John Smith', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John Doe'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertGreaterThan(0, $this->countUniqueIndexes($result));

        $words = [];
        foreach ($result as $item) {
            $words[] = $item->normalized->getValue();
        }

        $this->assertContains('john', $words);
        $this->assertContains('doe', $words);
    }

    public function test_find_candidates_with_case_insensitive(): void
    {
        $this->createIndex('John Doe', 'name');
        $this->createIndex('JOHN SMITH', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(2, $this->countUniqueIndexes($result));
    }

    public function test_find_candidates_with_empty_query(): void
    {
        $this->createIndex('John Doe', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from(''),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(0, $result->count());
    }

    public function test_find_candidates_returns_item_words_collection(): void
    {
        $this->createIndex('John Doe', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertInstanceOf(ItemWordsCollection::class, $result);

        foreach ($result as $item) {
            $this->assertNotNull($item->normalized);
            $this->assertNotNull($item->ngrams);
            $this->assertNotNull($item->max_score);
            $this->assertNotNull($item->search_index);
        }
    }

    public function test_find_candidates_with_ngrams_used(): void
    {
        $this->createIndex('Terminal App', 'name');
        $this->createIndex('Terminal Pro', 'name');
        $this->createIndex('App Terminal', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Terminal'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertGreaterThanOrEqual(3, $this->countUniqueIndexes($result));
    }

    public function test_find_candidates_with_source_column_filter(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'email', 'App\Models\User', '2');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
            'source_column' => StringVO::from('name'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(1, $this->countUniqueIndexes($result));
        $this->assertEquals('John Doe', $result->first()->search_index->original_text->getValue());
    }

    public function test_find_candidates_with_source_id_filter(): void
    {
        $this->createIndex('John Doe', 'name', 'App\Models\User', '1');
        $this->createIndex('Jane Smith', 'name', 'App\Models\User', '2');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
            'searchable_id' => StringVO::from('1'),
        ]);

        $result = $this->service->findCandidates($query);

        $this->assertEquals(1, $this->countUniqueIndexes($result));
        $this->assertEquals('1', $result->first()->search_index->searchable_id->getValue());
    }

    public function test_find_candidates_with_similarity_scoring(): void
    {
        $this->createIndex('Apple', 'name');
        $this->createIndex('Aple', 'name');
        $this->createIndex('Orange', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Apple'),
        ]);

        $result = $this->service->findCandidates($query);

        $words = [];
        foreach ($result as $item) {
            $words[] = $item->search_index->original_text->getValue();
        }

        $this->assertContains('Apple', $words);
        $this->assertContains('Aple', $words);
    }

    public function test_find_candidates_with_similarity_ranks_correctly(): void
    {
        $this->createIndex('Exact Match', 'name');
        $this->createIndex('Similar Name', 'name');
        $this->createIndex('Far Match', 'name');

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Exact Match'),
        ]);

        $result = $this->service->findCandidates($query);

        $first = $result->first();
        $this->assertEquals('Exact Match', $first->search_index->original_text->getValue());
    }

    private function createIndex(
        string $text,
        string $column = 'name',
        string $type = 'App\Models\User',
        string $id = '1'
    ): SearchIndex {
        $uris = $this->wordVectorParser->parse(explode(' ', strtolower($text)));
        $ngrams = $this->ngramService->generateFromText($text)->toArray();

        $record = SearchIndexRecord::from([
            'id' => UuidVO::generate(),
            'searchable_type' => $type,
            'searchable_id' => $id,
            'source_column' => $column,
            'original_text' => $text,
            'normalized_text' => $this->normalizer->normalize($text),
            'item_words' => $uris,
            'ngrams' => $ngrams,
        ]);

        return $this->repository->create($record);
    }
}
