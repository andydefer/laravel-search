<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\SearchIndexService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestAddress;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\LaravelSearch\ValueObjects\SearchCandidatesVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SearchIndexServiceTest extends IntegrationTestCase
{
    private SearchIndexService $service;

    private SearchIndexRepository $repository;

    private WordVectorParserService $wordVectorParser;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    private SearchConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->wordVectorParser = $this->app->make(WordVectorParserService::class);
        $this->config = $this->app->make(SearchConfig::class);
        $this->repository = new SearchIndexRepository($this->ngramService, $this->wordVectorParser, $this->config);

        $this->service = new SearchIndexService(
            $this->repository,
            $this->normalizer,
            $this->ngramService,
            $this->wordVectorParser,
        );
    }

    public function test_index_single_entity(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $collection = $this->service->index($user);

        $this->assertInstanceOf(SearchIndexCollection::class, $collection);
        $this->assertEquals(3, $collection->count());

        $sourceColumns = $collection->map(fn ($record) => $record->source_column->getValue())->toArray();
        $this->assertContains('name', $sourceColumns);
        $this->assertContains('email', $sourceColumns);
        $this->assertContains('description', $sourceColumns);

        foreach ($collection as $index) {
            $this->assertEquals(TestUser::class, $index->searchable_type->getValue());
            $this->assertEquals((string) $user->id, $index->searchable_id->getValue());
            $this->assertNotEmpty($index->item_words->toArray());
            $this->assertNotEmpty($index->ngrams->toArray());
        }
    }

    public function test_index_entity_with_empty_column(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => '',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $collection = $this->service->index($user);

        $this->assertEquals(2, $collection->count());

        $sourceColumns = $collection->map(fn ($record) => $record->source_column->getValue())->toArray();
        $this->assertContains('name', $sourceColumns);
        $this->assertContains('description', $sourceColumns);
        $this->assertNotContains('email', $sourceColumns);
    }

    public function test_index_all(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active user 1',
            'is_active' => true,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'description' => 'Active user 2',
            'is_active' => true,
        ]);
        TestUser::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'description' => 'Inactive user',
            'is_active' => false,
        ]);

        $count = $this->service->indexAll(TestUser::class);

        $this->assertEquals(2, $count);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);

        $indexedCount = $this->repository->countByFilters($filters);
        $this->assertEquals(6, $indexedCount);
    }

    public function test_index_all_with_callback(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active user 1',
            'is_active' => true,
        ]);

        $callbackCalled = false;

        $count = $this->service->indexAllWithCallback(
            TestUser::class,
            function ($entity, $count) use (&$callbackCalled) {
                $callbackCalled = true;
                $this->assertInstanceOf(TestUser::class, $entity);
                $this->assertEquals(1, $count);
            }
        );

        $this->assertTrue($callbackCalled);
        $this->assertEquals(1, $count);
    }

    public function test_reindex(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $collection = $this->service->index($user);
        $originalIds = $collection->map(fn ($record) => $record->id->getValue())->toArray();

        $user->update(['name' => 'John Smith']);

        $newCollection = $this->service->reindex($user);

        $this->assertEquals(3, $newCollection->count());

        $newIds = $newCollection->map(fn ($record) => $record->id->getValue())->toArray();
        foreach ($originalIds as $id) {
            $this->assertNotContains($id, $newIds);
        }

        $nameIndex = $newCollection->filter(fn ($record) => $record->source_column->getValue() === 'name')->first();
        $this->assertStringContainsString('John Smith', $nameIndex->original_text->getValue());
    }

    public function test_reindex_all(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active user',
            'is_active' => true,
        ]);

        $count = $this->service->indexAll(TestUser::class);
        $this->assertEquals(1, $count);

        $count = $this->service->reindexAll(TestUser::class);
        $this->assertEquals(1, $count);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);
        $indexedCount = $this->repository->countByFilters($filters);
        $this->assertEquals(3, $indexedCount);
    }

    public function test_delete(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $collection = $this->service->index($user);
        $firstId = $collection->first()->id->getValue();

        $found = $this->repository->find($firstId);
        $this->assertNotNull($found);

        $deleted = $this->service->delete($user);
        $this->assertTrue($deleted);

        $found = $this->repository->find($firstId);
        $this->assertNull($found);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
            'searchable_id' => StringVO::from((string) $user->id),
        ]);
        $remaining = $this->repository->countByFilters($filters);
        $this->assertEquals(0, $remaining);
    }

    public function test_delete_all(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active user',
            'is_active' => true,
        ]);
        TestProduct::create([
            'name' => 'Product 1',
            'reference' => 'REF001',
            'description' => 'Product description',
            'is_published' => true,
        ]);

        $this->service->indexAll(TestUser::class);
        $this->service->indexAll(TestProduct::class);

        $count = $this->service->deleteAll(TestUser::class);
        $this->assertEquals(3, $count);

        $filtersUser = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);
        $filtersProduct = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestProduct::class),
        ]);

        $usersCount = $this->repository->countByFilters($filtersUser);
        $productsCount = $this->repository->countByFilters($filtersProduct);

        $this->assertEquals(0, $usersCount);
        $this->assertEquals(3, $productsCount);
    }

    public function test_delete_by_id(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $collection = $this->service->index($user);
        $id = $collection->first()->id->getValue();

        $deleted = $this->service->deleteById($id);
        $this->assertTrue($deleted);

        $found = $this->repository->find($id);
        $this->assertNull($found);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
            'searchable_id' => StringVO::from((string) $user->id),
        ]);
        $remaining = $this->repository->countByFilters($filters);
        $this->assertEquals(2, $remaining);
    }

    public function test_delete_by_entity_id(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->service->index($user);

        $deleted = $this->service->deleteByEntityId(TestUser::class, (string) $user->id);
        $this->assertTrue($deleted);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
            'searchable_id' => StringVO::from((string) $user->id),
        ]);
        $remaining = $this->repository->countByFilters($filters);
        $this->assertEquals(0, $remaining);
    }

    public function test_get_indexed_count(): void
    {
        TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);
        TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);

        $this->service->indexAll(TestUser::class);

        $count = $this->service->getIndexedCount(TestUser::class);
        $this->assertEquals(2, $count);
    }

    public function test_get_not_indexed_count(): void
    {
        TestUser::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);
        TestUser::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'description' => 'Inactive',
            'is_active' => false,
        ]);

        $this->service->indexAll(TestUser::class);

        $notIndexed = $this->service->getNotIndexedCount(TestUser::class);
        $this->assertEquals(1, $notIndexed);
    }

    public function test_sync(): void
    {
        $user1 = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);
        $user2 = TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);
        $user3 = TestUser::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'description' => 'Inactive',
            'is_active' => false,
        ]);

        $this->service->index($user1);

        $result = $this->service->sync(TestUser::class);

        $this->assertEquals(2, $result->indexed);
        $this->assertEquals(0, $result->deleted);
        $this->assertEquals(1, $result->skipped);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);
        $indexed = $this->repository->countByFilters($filters);
        $this->assertEquals(6, $indexed);
    }

    public function test_sync_with_delete(): void
    {
        $user = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);

        $this->service->index($user);

        $user->update(['is_active' => false]);

        $result = $this->service->sync(TestUser::class);

        $this->assertEquals(0, $result->indexed);
        $this->assertEquals(1, $result->deleted);
        $this->assertEquals(0, $result->skipped);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestUser::class),
        ]);
        $indexed = $this->repository->countByFilters($filters);
        $this->assertEquals(0, $indexed);
    }

    public function test_generate_ngrams_from_words(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateNgramsFromWords');
        $method->setAccessible(true);

        $words = ['test', 'hello'];
        $result = $method->invoke($this->service, $words);

        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertNotEmpty($result->toArray());

        $ngrams = $result->toArray();
        $this->assertContains('te', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('test', $ngrams);
    }

    public function test_index_address_with_calculated_properties(): void
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

        $collection = $this->service->index($address);

        $this->assertEquals(7, $collection->count());

        $sourceColumns = $collection->map(fn ($record) => $record->source_column->getValue())->toArray();
        $this->assertContains('address_street', $sourceColumns);
        $this->assertContains('address_city', $sourceColumns);
        $this->assertContains('address_country', $sourceColumns);
        $this->assertContains('address_postal_code', $sourceColumns);
        $this->assertContains('user_name', $sourceColumns);
        $this->assertContains('user_email', $sourceColumns);
        $this->assertContains('full_address', $sourceColumns);

        foreach ($collection as $index) {
            $column = $index->source_column->getValue();
            $text = $index->original_text->getValue();

            match ($column) {
                'address_street' => $this->assertEquals('123 Main Street', $text),
                'address_city' => $this->assertEquals('Paris', $text),
                'address_country' => $this->assertEquals('France', $text),
                'address_postal_code' => $this->assertEquals('75001', $text),
                'user_name' => $this->assertEquals('John Doe', $text),
                'user_email' => $this->assertEquals('john@example.com', $text),
                'full_address' => $this->assertEquals('123 Main Street, Paris, France, 75001', $text),
                default => $this->fail("Unexpected column: {$column}"),
            };
        }
    }

    public function test_search_address_by_city(): void
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

        $this->service->index($address);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestAddress::class),
            'source_column' => StringVO::from('address_city'),
        ]);

        $words = StringTypedCollection::from(['paris']);
        $ngrams = StringTypedCollection::from($this->ngramService->generateFromText('paris')->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters);

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $this->wordVectorParser->parse(['paris']), 2);

        $this->assertCount(1, $results);
        $this->assertEquals('Paris', $results->first()->original_text->getValue());
    }

    public function test_search_address_by_country(): void
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

        $this->service->index($address);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestAddress::class),
            'source_column' => StringVO::from('address_country'),
        ]);

        $words = StringTypedCollection::from(['france']);
        $ngrams = StringTypedCollection::from($this->ngramService->generateFromText('france')->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters);

        $results = $this->repository->findCandidatesBySimilarity($candidatesVO, $this->wordVectorParser->parse(['france']), 2);

        $this->assertCount(1, $results);
        $this->assertEquals('France', $results->first()->original_text->getValue());
    }

    public function test_index_address_with_inactive_status(): void
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
            'is_active' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity should not be indexed');

        $this->service->index($address);
    }

    public function test_index_all_addresses(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        TestAddress::create([
            'user_id' => $user->id,
            'street' => '123 Main Street',
            'city' => 'Paris',
            'country' => 'France',
            'postal_code' => '75001',
            'is_active' => true,
        ]);

        TestAddress::create([
            'user_id' => $user->id,
            'street' => '456 Elm Street',
            'city' => 'Lyon',
            'country' => 'France',
            'postal_code' => '69001',
            'is_active' => true,
        ]);

        TestAddress::create([
            'user_id' => $user->id,
            'street' => '789 Oak Street',
            'city' => 'Marseille',
            'country' => 'France',
            'postal_code' => '13001',
            'is_active' => false,
        ]);

        $count = $this->service->indexAll(TestAddress::class);

        $this->assertEquals(2, $count);

        $filters = SearchIndexFiltersRecord::from([
            'searchable_type' => StringVO::from(TestAddress::class),
        ]);

        $indexedCount = $this->repository->countByFilters($filters);
        $this->assertEquals(14, $indexedCount);
    }
}
