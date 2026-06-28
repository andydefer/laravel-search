<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\SearchIndexService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
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

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->repository = new SearchIndexRepository($this->ngramService);

        $this->service = new SearchIndexService(
            $this->repository,
            $this->normalizer,
            $this->ngramService,
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
        $this->assertEquals(3, $collection->count()); // 3 colonnes: name, email, description

        // Vérifier les colonnes indexées
        $sourceColumns = $collection->map(fn ($record) => $record->source_column->getValue())->toArray();
        $this->assertContains('name', $sourceColumns);
        $this->assertContains('email', $sourceColumns);
        $this->assertContains('description', $sourceColumns);

        // Vérifier chaque index
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

        // Seulement 2 colonnes indexées (email est vide)
        $this->assertEquals(2, $collection->count());

        $sourceColumns = $collection->map(fn ($record) => $record->source_column->getValue())->toArray();
        $this->assertContains('name', $sourceColumns);
        $this->assertContains('description', $sourceColumns);
        $this->assertNotContains('email', $sourceColumns);
    }

    public function test_index_all(): void
    {
        // Créer des utilisateurs actifs
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

        // Seuls 2 utilisateurs actifs doivent être indexés
        $this->assertEquals(2, $count);

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
        );

        $indexedCount = $this->repository->countByFilters($filters);
        // 2 utilisateurs × 3 colonnes = 6 indexes
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

        // Indexer
        $collection = $this->service->index($user);
        $originalIds = $collection->map(fn ($record) => $record->id->getValue())->toArray();

        // Modifier
        $user->update(['name' => 'John Smith']);

        // Réindexer
        $newCollection = $this->service->reindex($user);

        $this->assertEquals(3, $newCollection->count());

        // Vérifier que les IDs sont différents
        $newIds = $newCollection->map(fn ($record) => $record->id->getValue())->toArray();
        foreach ($originalIds as $id) {
            $this->assertNotContains($id, $newIds);
        }

        // Vérifier que le nom a changé
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

        // Indexer
        $count = $this->service->indexAll(TestUser::class);
        $this->assertEquals(1, $count);

        // Réindexer tout
        $count = $this->service->reindexAll(TestUser::class);
        $this->assertEquals(1, $count);

        // Vérifier qu'il y a 3 indexes (3 colonnes)
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
        );
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

        // Vérifier que l'index existe
        $found = $this->repository->find($firstId);
        $this->assertNotNull($found);

        // Supprimer
        $deleted = $this->service->delete($user);
        $this->assertTrue($deleted);

        // Vérifier que tous les indexes ont été supprimés
        $found = $this->repository->find($firstId);
        $this->assertNull($found);

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
            searchable_id: StringVO::from((string) $user->id),
        );
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

        // Indexer
        $this->service->indexAll(TestUser::class);
        $this->service->indexAll(TestProduct::class);

        // Supprimer tous les users (3 indexes par user = 3)
        $count = $this->service->deleteAll(TestUser::class);
        $this->assertEquals(3, $count);

        // Vérifier que seuls les users ont été supprimés
        $filtersUser = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
        );
        $filtersProduct = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestProduct::class),
        );

        $usersCount = $this->repository->countByFilters($filtersUser);
        $productsCount = $this->repository->countByFilters($filtersProduct);

        $this->assertEquals(0, $usersCount);
        $this->assertEquals(3, $productsCount); // 3 colonnes pour Product
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

        // Vérifier que les autres indexes existent encore
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
            searchable_id: StringVO::from((string) $user->id),
        );
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

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
            searchable_id: StringVO::from((string) $user->id),
        );
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
        // 2 entités uniques
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
        // Créer des utilisateurs
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

        // Indexer seulement User 1
        $this->service->index($user1);

        // Synchroniser tout
        $result = $this->service->sync(TestUser::class);

        // User 1 déjà indexé → reindexé, User 2 nouvellement indexé, User 3 ignoré (shouldBeIndexed false)
        $this->assertEquals(2, $result['indexed']); // User1 + User2
        $this->assertEquals(0, $result['deleted']);
        $this->assertEquals(1, $result['skipped']); // User3 (inactif)

        // Vérifier que 2 utilisateurs × 3 colonnes = 6 indexes
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
        );
        $indexed = $this->repository->countByFilters($filters);
        $this->assertEquals(6, $indexed);
    }

    public function test_sync_with_delete(): void
    {
        // Créer un utilisateur actif
        $user = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'description' => 'Active',
            'is_active' => true,
        ]);

        // Indexer
        $this->service->index($user);

        // Rendre inactif
        $user->update(['is_active' => false]);

        // Synchroniser
        $result = $this->service->sync(TestUser::class);

        $this->assertEquals(0, $result['indexed']);
        $this->assertEquals(1, $result['deleted']);
        $this->assertEquals(0, $result['skipped']);

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestUser::class),
        );
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

        $this->assertInstanceOf(Sequential::class, $result);
        $this->assertNotEmpty($result->toArray());

        $ngrams = $result->toArray();
        $this->assertContains('te', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('test', $ngrams);
    }

    // ============================================================
    // TESTS POUR LES PROPRIÉTÉS CALCULÉES (ADDRESS)
    // ============================================================

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

        // Charger la relation avant l'indexation
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

        // Vérifier les données pour chaque colonne
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

        // Rechercher par ville
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestAddress::class),
            source_column: StringVO::from('address_city'),
        );

        $words = Sequential::from(['paris']);
        $ngrams = Sequential::from($this->ngramService->generateFromText('paris')->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, 10);

        $results = $this->repository->findCandidates($candidatesVO);

        $this->assertCount(1, $results);
        $this->assertEquals('Paris', $results->first()->original_text);
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

        // Rechercher par pays
        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestAddress::class),
            source_column: StringVO::from('address_country'),
        );

        $words = Sequential::from(['france']);
        $ngrams = Sequential::from($this->ngramService->generateFromText('france')->toArray());

        $candidatesVO = new SearchCandidatesVO($words, $ngrams, $filters, 10);

        $results = $this->repository->findCandidates($candidatesVO);

        $this->assertCount(1, $results);
        $this->assertEquals('France', $results->first()->original_text);
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

        // 2 adresses actives
        $this->assertEquals(2, $count);

        $filters = new SearchIndexFiltersRecord(
            searchable_type: StringVO::from(TestAddress::class),
        );

        // 2 adresses × 7 colonnes = 14 indexes
        $indexedCount = $this->repository->countByFilters($filters);
        $this->assertEquals(14, $indexedCount);
    }
}
