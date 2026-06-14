<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Repositories;

use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Repositories\SearchIndexRepository;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;

final class SearchIndexRepositoryTest extends IntegrationTestCase
{
    private SearchIndexRepository $repository;
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SearchIndexRepository();

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        SearchIndex::truncate();
        parent::tearDown();
    }

    private function createSearchIndexRecord(array $overrides = []): SearchIndexRecord
    {
        $defaults = [
            'searchable_type' => TestUser::class,
            'searchable_id' => (string) $this->user->id,
            'content' => 'John Doe john@example.com',
            'normalized_content' => 'john doe john@example.com',
            'fields' => new SearchableFieldCollection(),
        ];

        $data = array_merge($defaults, $overrides);

        return new SearchIndexRecord(
            searchable_type: $data['searchable_type'],
            searchable_id: $data['searchable_id'],
            content: $data['content'],
            normalized_content: $data['normalized_content'],
            fields: $data['fields'],
        );
    }

    // ==================== Tests: updateOrCreate ====================

    public function test_updateOrCreate_creates_new_record(): void
    {
        $record = $this->createSearchIndexRecord();

        $model = $this->repository->updateOrCreate($record);

        $this->assertNotNull($model);
        $this->assertSame(TestUser::class, $model->searchable_type);
        $this->assertSame((string) $this->user->id, $model->searchable_id);
        $this->assertSame('John Doe john@example.com', $model->content);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_updateOrCreate_updates_existing_record(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $updatedRecord = $this->createSearchIndexRecord([
            'content' => 'Jane Smith jane@example.com',
            'normalized_content' => 'jane smith jane@example.com',
        ]);

        $model = $this->repository->updateOrCreate($updatedRecord);

        $this->assertNotNull($model);
        $this->assertSame(1, SearchIndex::count());
        $this->assertSame('Jane Smith jane@example.com', $model->content);
    }

    public function test_updateOrCreate_preserves_existing_fields_when_updating(): void
    {
        $fields = new SearchableFieldCollection();
        $record = $this->createSearchIndexRecord(['fields' => $fields]);
        $this->repository->updateOrCreate($record);

        $updatedRecord = $this->createSearchIndexRecord(['content' => 'Updated content']);
        $model = $this->repository->updateOrCreate($updatedRecord);

        $this->assertNotNull($model);
        $this->assertIsArray($model->fields);
    }

    // ==================== Tests: findBy avec filtres ====================

    public function test_findBy_with_searchable_type_filter(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(searchable_type: TestUser::class);
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_with_searchable_id_filter(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(searchable_id: (string) $this->user->id);
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_with_content_filter(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(content: 'John');
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertStringContainsString('John', $results->first()->content);
    }

    public function test_findBy_with_normalized_content_filter(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(normalized_content: 'john');
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_with_search_filter_on_content(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(search: 'Doe');
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_with_search_filter_on_normalized_content(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(search: 'john@example.com');
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_with_multiple_filters(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(
            searchable_type: TestUser::class,
            searchable_id: (string) $this->user->id,
        );
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    public function test_findBy_returns_empty_collection_when_no_match(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(searchable_type: 'App\Models\NonExistent');
        $findBy = new FindByRecord(filters: $filters);

        $results = $this->repository->findBy($findBy);

        $this->assertCount(0, $results);
    }

    // ==================== Tests: findBy avec colonnes ====================

    public function test_findBy_with_select_columns(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(searchable_type: TestUser::class);
        $findBy = new FindByRecord(
            filters: $filters,
            columns: new SelectColumns(['id', 'searchable_type']),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('id', $results->first()->toArray());
        $this->assertArrayHasKey('searchable_type', $results->first()->toArray());
        $this->assertArrayNotHasKey('content', $results->first()->toArray());
    }

    // ==================== Tests: findBy avec limite ====================

    public function test_findBy_with_limit(): void
    {
        $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $record1 = $this->createSearchIndexRecord();
        $record2 = $this->createSearchIndexRecord([
            'searchable_id' => (string) $user2->id,
            'content' => 'Jane Smith jane@example.com',
            'normalized_content' => 'jane smith jane@example.com',
        ]);

        $this->repository->updateOrCreate($record1);
        $this->repository->updateOrCreate($record2);

        $filters = new SearchIndexFiltersRecord();
        $findBy = new FindByRecord(
            filters: $filters,
            limit: 1,
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
    }

    // ==================== Tests: deleteBulk ====================

    public function test_deleteBulk_removes_matching_records(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $this->assertSame(1, SearchIndex::count());

        $filters = new SearchIndexFiltersRecord(searchable_type: TestUser::class);
        $this->repository->deleteBulk($filters);

        $this->assertSame(0, SearchIndex::count());
    }

    public function test_deleteBulk_without_filters_removes_nothing(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord();
        $deletedCount = $this->repository->deleteBulk($filters);

        $this->assertSame(0, $deletedCount);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_deleteBulk_with_non_matching_filters_removes_nothing(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord(content: 'NonExistentContent');
        $deletedCount = $this->repository->deleteBulk($filters);

        $this->assertSame(0, $deletedCount);
        $this->assertSame(1, SearchIndex::count());
    }

    // ==================== Tests: forceDeleteBulk ====================

    public function test_forceDeleteBulk_removes_matching_records(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $this->assertSame(1, SearchIndex::count());

        $filters = new SearchIndexFiltersRecord(searchable_type: TestUser::class);
        $this->repository->forceDeleteBulk($filters);

        $this->assertSame(0, SearchIndex::count());
    }

    public function test_forceDeleteBulk_without_filters_removes_nothing(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $filters = new SearchIndexFiltersRecord();
        $deletedCount = $this->repository->forceDeleteBulk($filters);

        $this->assertSame(0, $deletedCount);
        $this->assertSame(1, SearchIndex::count());
    }

    // ==================== Tests: deleteAll ====================

    public function test_deleteAll_removes_all_records(): void
    {
        $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $record1 = $this->createSearchIndexRecord();
        $record2 = $this->createSearchIndexRecord([
            'searchable_id' => (string) $user2->id,
            'content' => 'Jane Smith jane@example.com',
            'normalized_content' => 'jane smith jane@example.com',
        ]);

        $this->repository->updateOrCreate($record1);
        $this->repository->updateOrCreate($record2);

        $this->assertSame(2, SearchIndex::count());

        $deletedCount = $this->repository->deleteAll();

        $this->assertSame(2, $deletedCount);
        $this->assertSame(0, SearchIndex::count());
    }

    public function test_deleteAll_returns_zero_when_table_is_empty(): void
    {
        $this->assertSame(0, SearchIndex::count());

        $deletedCount = $this->repository->deleteAll();

        $this->assertSame(0, $deletedCount);
        $this->assertSame(0, SearchIndex::count());
    }

    // ==================== Tests: forceDeleteAll ====================

    public function test_forceDeleteAll_removes_all_records(): void
    {
        $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $record1 = $this->createSearchIndexRecord();
        $record2 = $this->createSearchIndexRecord([
            'searchable_id' => (string) $user2->id,
            'content' => 'Jane Smith jane@example.com',
            'normalized_content' => 'jane smith jane@example.com',
        ]);

        $this->repository->updateOrCreate($record1);
        $this->repository->updateOrCreate($record2);

        $this->assertSame(2, SearchIndex::count());

        $deletedCount = $this->repository->forceDeleteAll();

        $this->assertSame(2, $deletedCount);
        $this->assertSame(0, SearchIndex::count());
    }

    public function test_forceDeleteAll_returns_zero_when_table_is_empty(): void
    {
        $this->assertSame(0, SearchIndex::count());

        $deletedCount = $this->repository->forceDeleteAll();

        $this->assertSame(0, $deletedCount);
        $this->assertSame(0, SearchIndex::count());
    }

    // ==================== Tests: find ====================

    public function test_find_returns_record_by_id(): void
    {
        $record = $this->createSearchIndexRecord();
        $model = $this->repository->updateOrCreate($record);

        $found = $this->repository->find($model->id);

        $this->assertNotNull($found);
        $this->assertSame($model->id, $found->id);
        $this->assertSame(TestUser::class, $found->searchable_type);
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $found = $this->repository->find(99999);

        $this->assertNull($found);
    }

    // ==================== Tests: countAll ====================

    public function test_countAll_returns_total_number_of_records(): void
    {
        $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $record1 = $this->createSearchIndexRecord();
        $record2 = $this->createSearchIndexRecord([
            'searchable_id' => (string) $user2->id,
            'content' => 'Jane Smith jane@example.com',
            'normalized_content' => 'jane smith jane@example.com',
        ]);

        $this->repository->updateOrCreate($record1);
        $this->repository->updateOrCreate($record2);

        $this->assertSame(2, $this->repository->countAll());
    }

    public function test_countAll_returns_zero_when_table_is_empty(): void
    {
        $this->assertSame(0, $this->repository->countAll());
    }

    // ==================== Tests: existsForModel ====================

    public function test_existsForModel_returns_true_when_record_exists(): void
    {
        $record = $this->createSearchIndexRecord();
        $this->repository->updateOrCreate($record);

        $exists = $this->repository->existsForModel(TestUser::class, (string) $this->user->id);

        $this->assertTrue($exists);
    }

    public function test_existsForModel_returns_false_when_record_does_not_exist(): void
    {
        $exists = $this->repository->existsForModel(TestUser::class, '99999');

        $this->assertFalse($exists);
    }

    // ==================== Tests: findByModel ====================

    public function test_findByModel_returns_record_when_exists(): void
    {
        $record = $this->createSearchIndexRecord();
        $expected = $this->repository->updateOrCreate($record);

        $found = $this->repository->findByModel(TestUser::class, (string) $this->user->id);

        $this->assertNotNull($found);
        $this->assertSame($expected->id, $found->id);
        $this->assertSame($expected->content, $found->content);
    }

    public function test_findByModel_returns_null_when_record_does_not_exist(): void
    {
        $found = $this->repository->findByModel(TestUser::class, '99999');

        $this->assertNull($found);
    }
}
