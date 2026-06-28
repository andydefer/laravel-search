<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\LaravelSearch\Collections\SourceColumnCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Records\SearchResultCollectionRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\SearchIndexService;
use AndyDefer\LaravelSearch\Services\SearchService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestAddress;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestNonSearchableModel;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SearchServiceTest extends IntegrationTestCase
{
    private SearchService $service;

    private SearchIndexRepository $repository;

    private QueryProcessorService $queryProcessor;

    private SearchIndexService $indexService;

    private SearchConfig $config;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    private WordVectorParserService $wordVectorParser;

    protected function setUp(): void
    {
        parent::setUp();

        $configRepository = app(ConfigRepository::class);
        $this->config = new SearchConfig($configRepository);
        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->wordVectorParser = $this->app->make(WordVectorParserService::class);
        $this->repository = $this->app->make(SearchIndexRepository::class);
        $this->queryProcessor = new QueryProcessorService($this->config, $this->normalizer, $this->ngramService);

        $this->indexService = new SearchIndexService(
            $this->repository,
            $this->normalizer,
            $this->ngramService,
            $this->wordVectorParser,
        );

        $this->service = $this->app->make(SearchService::class);
    }

    public function test_search_returns_empty_result_when_query_empty(): void
    {
        $query = SearchQueryRecord::from([
            'query' => StringVO::from(''),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertInstanceOf(SearchResultCollectionRecord::class, $result);
        $this->assertEquals(0, $result->total);
        $this->assertEquals(0.0, $result->max_percentage->getValue());
        $this->assertEquals(0.0, $result->avg_percentage->getValue());
    }

    public function test_search_returns_empty_result_when_no_match(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('xyz'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(0, $result->total);
    }

    public function test_search_with_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $user = TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'description' => "User {$i} description",
                'is_active' => true,
            ]);
            $this->indexService->index($user);
        }

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('User'),
            'limit' => 5,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchWithLimit($query, 5, 0);

        $this->assertInstanceOf(SearchResultCollectionRecord::class, $result);
        $this->assertEquals(5, $result->limit);
        $this->assertEquals(5, $result->total);
    }

    public function test_search_with_filters(): void
    {
        $user1 = TestUser::create([
            'name' => 'User John',
            'email' => 'john@example.com',
            'description' => 'User John description',
            'is_active' => true,
        ]);

        $user2 = TestUser::create([
            'name' => 'User Jane',
            'email' => 'jane@example.com',
            'description' => 'User Jane description',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'Product A',
            'reference' => 'PROD-A',
            'description' => 'Product description',
            'is_published' => true,
        ]);

        $this->indexService->index($user1);
        $this->indexService->index($user2);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('User'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);

        $result = $this->service->searchWithFilters($query, $filters);

        $this->assertInstanceOf(SearchResultCollectionRecord::class, $result);
        $this->assertEquals(2, $result->total);
    }

    public function test_search_exact_match(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John Doe'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(100.0, $result->max_percentage->getValue());
        $this->assertEquals('John Doe', $result->results->first()->search_index->original_text->getValue());
    }

    public function test_search_partial_match(): void
    {
        $user1 = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $user2 = TestUser::create([
            'name' => 'John Johnson',
            'email' => 'johnson@example.com',
            'description' => 'Manager',
            'is_active' => true,
        ]);

        $user3 = TestUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'description' => 'Designer',
            'is_active' => true,
        ]);

        $this->indexService->index($user1);
        $this->indexService->index($user2);
        $this->indexService->index($user3);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(2, $result->total);
        $this->assertGreaterThan(0, $result->max_percentage->getValue());
        $this->assertGreaterThan(0, $result->avg_percentage->getValue());
    }

    public function test_search_with_accents(): void
    {
        $user1 = TestUser::create([
            'name' => 'Jean-Pierre',
            'email' => 'jp@example.com',
            'description' => 'Developer',
            'is_active' => true,
        ]);

        $user2 = TestUser::create([
            'name' => 'Jean',
            'email' => 'jean@example.com',
            'description' => 'Designer',
            'is_active' => true,
        ]);

        $this->indexService->index($user1);
        $this->indexService->index($user2);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Jean'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(2, $result->total);
        $this->assertGreaterThan(0, $result->max_percentage->getValue());
    }

    public function test_search_result_collection_structure(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John Doe'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertInstanceOf(SearchResultCollectionRecord::class, $result);
        $this->assertArrayHasKey('results', $result->toArray());
        $this->assertArrayHasKey('total', $result->toArray());
        $this->assertArrayHasKey('max_percentage', $result->toArray());
        $this->assertArrayHasKey('avg_percentage', $result->toArray());
        $this->assertArrayHasKey('query', $result->toArray());
        $this->assertArrayHasKey('limit', $result->toArray());
    }

    public function test_search_by_type(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'MacBook Pro',
            'reference' => 'MBP-2024',
            'description' => 'Ordinateur portable',
            'is_published' => true,
        ]);

        $this->indexService->index($user);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('software'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchByType($query, StringVO::from(TestUser::class));

        $this->assertEquals(1, $result->total);
        $this->assertEquals('John Doe', $result->results->first()->data->name);
    }

    public function test_search_by_column(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchByColumn($query, StringVO::from('email'));

        $this->assertEquals(1, $result->total);
        $this->assertEquals('john@example.com', $result->results->first()->data->email);
    }

    public function test_search_by_columns(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $columns = new SourceColumnCollection;
        $columns->add(StringVO::from('name'));
        $columns->add(StringVO::from('email'));

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchByColumns($query, $columns);

        $this->assertEquals(1, $result->total);
        $this->assertEquals('John Doe', $result->results->first()->data->name);
    }

    public function test_search_by_type_and_column(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'John Deere Tractor',
            'reference' => 'JD-2024',
            'description' => 'Tractor',
            'is_published' => true,
        ]);

        $this->indexService->index($user);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchByTypeAndColumn(
            $query,
            StringVO::from(TestUser::class),
            StringVO::from('name')
        );

        $this->assertEquals(1, $result->total);
        $this->assertEquals('John Doe', $result->results->first()->data->name);
    }

    public function test_search_excluding_type(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'John Deere Tractor',
            'reference' => 'JD-2024',
            'description' => 'Tractor for farming',
            'is_published' => true,
        ]);

        $this->indexService->index($user);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchExcludingType($query, StringVO::from(TestProduct::class));

        $this->assertEquals(1, $result->total);
        $this->assertEquals('John Doe', $result->results->first()->data->name);
    }

    public function test_search_excluding_column(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchExcludingColumn($query, StringVO::from('name'));

        $this->assertEquals(0, $result->total);
    }

    public function test_search_excluding_type_and_column(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'John Deere Tractor',
            'reference' => 'JD-2024',
            'description' => 'Tractor',
            'is_published' => true,
        ]);

        $this->indexService->index($user);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $result = $this->service->searchExcludingTypeAndColumn(
            $query,
            StringVO::from(TestUser::class),
            StringVO::from('name')
        );

        $this->assertEquals(0, $result->total);
    }

    public function test_search_advanced(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('john'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
            'source_column' => StringVO::from('name'),
        ]);

        $result = $this->service->searchAdvanced($query, $filters);

        $this->assertEquals(1, $result->total);
        $this->assertEquals('John Doe', $result->results->first()->data->name);
    }

    public function test_search_user_with_formatted_data(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->indexService->index($user);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(1, $result->total);

        $firstResult = $result->results->first();
        $this->assertNotNull($firstResult->data);

        $data = $firstResult->data;
        $this->assertEquals($user->id, $data->id);
        $this->assertEquals('John Doe', $data->name);
        $this->assertEquals('john@example.com', $data->email);
        $this->assertEquals('Software Developer', $data->description);
        $this->assertEquals(true, $data->is_active);
        $this->assertNotNull($data->created_at);
    }

    public function test_search_address_with_formatted_data(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $address = TestAddress::create([
            'user_id' => $user->id,
            'street' => '123 Main Street',
            'city' => 'Paris',
            'country' => 'France',
            'postal_code' => '75001',
            'is_active' => true,
        ]);

        $address->load('user');

        $this->indexService->index($address);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('Paris'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(1, $result->total);

        $firstResult = $result->results->first();
        $this->assertNotNull($firstResult->data);

        $data = $firstResult->data;
        $this->assertEquals($address->id, $data->id);
        $this->assertEquals($address->user_id, $data->user_id);
        $this->assertEquals('123 Main Street', $data->street);
        $this->assertEquals('Paris', $data->city);
        $this->assertEquals('France', $data->country);
        $this->assertEquals('75001', $data->postal_code);
        $this->assertEquals('123 Main Street, Paris, France, 75001', $data->full_address);
        $this->assertEquals(true, $data->is_active);
    }

    public function test_search_product_with_formatted_data(): void
    {
        $product = TestProduct::create([
            'name' => 'MacBook Pro',
            'reference' => 'MBP-2024',
            'description' => 'Ordinateur portable professionnel',
            'is_published' => true,
        ]);

        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('MacBook'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(1, $result->total);

        $firstResult = $result->results->first();
        $this->assertNotNull($firstResult->data);

        $data = $firstResult->data;
        $this->assertEquals($product->id, $data->id);
        $this->assertEquals('MacBook Pro', $data->name);
        $this->assertEquals('MBP-2024', $data->reference);
        $this->assertEquals('Ordinateur portable professionnel', $data->description);
        $this->assertEquals(true, $data->is_published);
        $this->assertNotNull($data->created_at);
    }

    public function test_search_multiple_entity_types(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $product = TestProduct::create([
            'name' => 'John Deere Tractor',
            'reference' => 'JD-2024',
            'description' => 'Tractor for farming',
            'is_published' => true,
        ]);

        $this->indexService->index($user);
        $this->indexService->index($product);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('John'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(20),
        ]);

        $result = $this->service->search($query);

        $this->assertEquals(2, $result->total);

        $results = $result->results->toArray();

        foreach ($results as $record) {
            $this->assertNotNull($record->data);
            $this->assertNotNull($record->search_index);
            $this->assertGreaterThan(0, $record->percentage->getValue());
        }
    }

    public function test_search_with_no_searchable_model_throws_exception(): void
    {
        $uris = $this->wordVectorParser->parse(['test']);
        $ngrams = $this->ngramService->generate('test')->toArray();

        $record = SearchIndexRecord::from([
            'id' => UuidVO::generate(),
            'searchable_type' => 'NonExistentClass',
            'searchable_id' => '1',
            'source_column' => 'name',
            'original_text' => 'Test',
            'normalized_text' => 'test',
            'item_words' => $uris,
            'ngrams' => $ngrams,
        ]);

        $this->repository->create($record);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('test'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Class NonExistentClass does not exist');

        $this->service->search($query);
    }

    public function test_search_with_model_not_implementing_searchable_throws_exception(): void
    {
        $model = TestNonSearchableModel::create([
            'name' => 'Test',
        ]);

        $uris = $this->wordVectorParser->parse(['test']);
        $ngrams = $this->ngramService->generate('test')->toArray();

        $record = SearchIndexRecord::from([
            'id' => UuidVO::generate(),
            'searchable_type' => 'AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestNonSearchableModel',
            'searchable_id' => (string) $model->id,
            'source_column' => 'name',
            'original_text' => 'Test',
            'normalized_text' => 'test',
            'item_words' => $uris,
            'ngrams' => $ngrams,
        ]);

        $this->repository->create($record);

        $query = SearchQueryRecord::from([
            'query' => StringVO::from('test'),
            'limit' => 10,
            'min_percentage' => FloatVO::from(0),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must implement AndyDefer\LaravelSearch\Contracts\Searchable');

        $this->service->search($query);
    }

    private function createIndex(
        string $text,
        string $column = 'name',
        string $type = 'App\Models\User',
        string $id = '1'
    ): SearchIndex {
        $words = $this->wordVectorParser->parse(explode(' ', strtolower($text)));
        $uris = $this->wordVectorParser->unparse($words);
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
