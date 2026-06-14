<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelSearch\Contracts\Services\IndexServiceInterface;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Services\IndexService;
use AndyDefer\LaravelSearch\Services\NormalizerService;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestPost;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use Mockery;

final class IndexServiceTest extends IntegrationTestCase
{
    private IndexServiceInterface $indexService;
    private TestUser $user;
    private TestPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(\AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface::class);
        $repository = $this->app->make(\AndyDefer\LaravelSearch\Repositories\SearchIndexRepository::class);
        $normalizer = $this->app->make(NormalizerService::class);
        $hydration = new HydrationService();

        $this->indexService = new IndexService($config, $repository, $normalizer, $hydration);

        // Créer l'utilisateur d'abord
        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // CORRECTION: Créer le post avec un user_id valide
        $this->post = TestPost::create([
            'user_id' => $this->user->id,  // Ajout de user_id requis
            'title' => 'Hello World',
            'body' => 'This is a test post content',
        ]);
    }

    protected function tearDown(): void
    {
        SearchIndex::truncate();
        Mockery::close();
        parent::tearDown();
    }

    // ==================== Tests: index() ====================

    public function test_index_creates_new_index_entry(): void
    {
        $result = $this->indexService->index($this->user);

        $this->assertTrue($result);
        $this->assertSame(1, SearchIndex::count());

        $index = SearchIndex::first();
        $this->assertSame(TestUser::class, $index->searchable_type);
        $this->assertSame((string) $this->user->id, $index->searchable_id);
        $this->assertStringContainsString('John Doe', $index->content);
        $this->assertStringContainsString('john@example.com', $index->content);
    }

    public function test_index_returns_false_when_already_indexed_without_force(): void
    {
        $this->indexService->index($this->user);
        $result = $this->indexService->index($this->user);

        $this->assertFalse($result);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_index_returns_true_when_force_reindex(): void
    {
        $this->indexService->index($this->user);
        $result = $this->indexService->index($this->user, force: true);

        $this->assertTrue($result);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_index_updates_content_when_force_reindex(): void
    {
        $this->indexService->index($this->user);

        // Modifier l'utilisateur
        $this->user->name = 'Jane Smith';
        $this->user->save();

        // Réindexer avec force
        $this->indexService->index($this->user, force: true);

        $index = SearchIndex::first();
        $this->assertStringContainsString('Jane Smith', $index->content);
    }

    public function test_index_normalizes_content(): void
    {
        $user = TestUser::create([
            'name' => 'Élève Français',
            'email' => 'eleve@example.com',
        ]);

        $this->indexService->index($user);

        $index = SearchIndex::first();
        $this->assertStringContainsString('eleve francais', strtolower($index->normalized_content));
    }

    // ==================== Tests: updateIndex() ====================

    public function test_updateIndex_forces_reindex(): void
    {
        $this->indexService->index($this->user);
        $this->user->name = 'Jane Smith';
        $this->user->save();

        $this->indexService->updateIndex($this->user);

        $index = SearchIndex::first();
        $this->assertStringContainsString('Jane Smith', $index->content);
    }

    // ==================== Tests: deleteIndex() ====================

    public function test_deleteIndex_removes_index_entry(): void
    {
        $this->indexService->index($this->user);
        $this->assertSame(1, SearchIndex::count());

        $this->indexService->deleteIndex($this->user);

        $this->assertSame(0, SearchIndex::count());
    }

    public function test_deleteIndex_does_nothing_for_nonexistent_entry(): void
    {
        $this->assertSame(0, SearchIndex::count());

        $this->indexService->deleteIndex($this->user);

        $this->assertSame(0, SearchIndex::count());
    }

    // ==================== Tests: indexAll() ====================

    public function test_indexAll_indexes_all_models(): void
    {
        $models = new StringTypedCollection();
        $models->add(TestUser::class);
        $models->add(TestPost::class);

        $stats = $this->indexService->indexAll($models);

        $this->assertSame(2, $stats->getValue()->indexed);
        $this->assertSame(0, $stats->getValue()->skipped);
        $this->assertSame(0, $stats->getValue()->errors);
        $this->assertSame(2, SearchIndex::count());
    }

    public function test_indexAll_with_force_reindexes_existing_entries(): void
    {
        $models = new StringTypedCollection();
        $models->add(TestUser::class);

        $this->indexService->indexAll($models);
        $this->assertSame(1, SearchIndex::count());

        $stats = $this->indexService->indexAll($models, force: true);

        $this->assertSame(1, $stats->getValue()->indexed);
        $this->assertSame(0, $stats->getValue()->skipped);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_indexAll_without_force_skips_existing_entries(): void
    {
        $models = new StringTypedCollection();
        $models->add(TestUser::class);

        $this->indexService->indexAll($models);
        $stats = $this->indexService->indexAll($models);

        $this->assertSame(0, $stats->getValue()->indexed);
        $this->assertSame(1, $stats->getValue()->skipped);
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_indexAll_with_callback_receives_progress(): void
    {
        $models = new StringTypedCollection();
        $models->add(TestUser::class);
        $models->add(TestPost::class);

        $calledModels = [];
        $calledIsNew = [];

        $stats = $this->indexService->indexAll($models, false, function ($model, $isNew) use (&$calledModels, &$calledIsNew) {
            $calledModels[] = get_class($model);
            $calledIsNew[] = $isNew;
        });

        $this->assertCount(2, $calledModels);
        $this->assertContains(TestUser::class, $calledModels);
        $this->assertContains(TestPost::class, $calledModels);
        $this->assertTrue(in_array(true, $calledIsNew));
    }

    public function test_indexAll_throws_exception_for_nonexistent_model(): void
    {
        $models = new StringTypedCollection();
        $models->add('NonExistentModel');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class not found: NonExistentModel');

        $this->indexService->indexAll($models);
    }

    public function test_indexAll_throws_exception_for_non_searchable_model(): void
    {
        $models = new StringTypedCollection();
        $models->add(\stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->indexService->indexAll($models);
    }

    public function test_indexAll_respects_shouldBeIndexed_method(): void
    {
        // Créer un utilisateur avec shouldBeIndexed = false
        $user = new class extends TestUser {
            public function shouldBeIndexed(): bool
            {
                return false;
            }
        };
        $user->name = 'Hidden User';
        $user->email = 'hidden@example.com';
        $user->save();

        $models = new StringTypedCollection();
        $models->add(get_class($user));

        $stats = $this->indexService->indexAll($models);

        $this->assertSame(0, $stats->getValue()->indexed);
    }

    // ==================== Tests: clearIndex() ====================

    public function test_clearIndex_removes_all_index_entries(): void
    {
        $models = new StringTypedCollection();
        $models->add(TestUser::class);
        $models->add(TestPost::class);

        $this->indexService->indexAll($models);
        $this->assertSame(2, SearchIndex::count());

        $this->indexService->clearIndex();

        $this->assertSame(0, SearchIndex::count());
    }

    // ==================== Tests: Performance et lots ====================

    public function test_indexAll_processes_in_batches(): void
    {
        // Créer 150 utilisateurs
        for ($i = 0; $i < 150; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $models = new StringTypedCollection();
        $models->add(TestUser::class);

        $stats = $this->indexService->indexAll($models);

        // +1 du setUp (l'utilisateur initial)
        $this->assertSame(151, $stats->getValue()->indexed);
        $this->assertSame(151, SearchIndex::count());
    }
}
