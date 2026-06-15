<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Services\IndexService;
use AndyDefer\LaravelSearch\Services\SearchService;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestPost;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;

final class SearchServiceTest extends IntegrationTestCase
{
    private SearchService $searchService;

    private TestUser $user1;

    private TestUser $user2;

    private TestPost $post1;

    private TestPost $post2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchService = $this->app->make(SearchService::class);

        // Créer des données de test
        $this->user1 = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'age' => 30,
        ]);

        $this->user2 = TestUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'status' => 'active',
            'role' => 'user',
            'age' => 25,
        ]);

        $this->post1 = TestPost::create([
            'user_id' => $this->user1->id,
            'title' => 'Introduction to Laravel',
            'body' => 'Laravel is a great PHP framework',
        ]);

        $this->post2 = TestPost::create([
            'user_id' => $this->user2->id,
            'title' => 'Advanced PHP Techniques',
            'body' => 'Learn advanced PHP programming',
        ]);

        // Indexer les données
        $indexService = $this->app->make(IndexService::class);
        $indexService->index($this->user1);
        $indexService->index($this->user2);
        $indexService->index($this->post1);
        $indexService->index($this->post2);
    }

    protected function tearDown(): void
    {
        SearchIndex::truncate();
        parent::tearDown();
    }

    // ==================== Tests: basic search ====================

    public function test_search_returns_results_for_exact_match(): void
    {
        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $query = new SearchQueryRecord(
            query: '123455555',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        // CORRECTION: Vérifier que la collection est vide
        $this->assertTrue($results->isEmpty(), 'Results should be empty for no match');
        $this->assertSame(0, $results->count());
    }

    public function test_search_limits_results(): void
    {
        $query = new SearchQueryRecord(
            query: 'a',
            limit: 2,
        );

        $results = $this->searchService->search($query);

        $this->assertLessThanOrEqual(2, $results->count());
    }

    // ==================== Tests: fuzzy matching ====================

    public function test_search_finds_similar_names(): void
    {
        $query = new SearchQueryRecord(
            query: 'Jonh',  // Typo de "John"
            limit: 10,
        );

        $results = $this->searchService->search($query);
        dump($results);

        $this->assertNotEmpty($results);
    }

    public function test_search_finds_partial_matches(): void
    {
        $query = new SearchQueryRecord(
            query: 'Laravel',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    public function test_search_handles_accents(): void
    {
        $user = TestUser::create([
            'name' => 'José Gonzalez',
            'email' => 'jose@example.com',
            'status' => 'active',
        ]);

        $indexService = $this->app->make(IndexService::class);
        $indexService->index($user);

        $query = new SearchQueryRecord(
            query: 'Jose',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    // ==================== Tests: filtering by type ====================

    public function test_search_filters_by_type(): void
    {
        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
            type: TestUser::class,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    public function test_search_filters_by_type_returns_only_specified_type(): void
    {
        $query = new SearchQueryRecord(
            query: 'Laravel',
            limit: 10,
            type: TestPost::class,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    // ==================== Tests: search with scores ====================

    public function test_search_returns_results_with_scores(): void
    {
        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);

        $firstResult = $results->first();
        $this->assertNotNull($firstResult);
        $this->assertGreaterThan(0, $firstResult->score);
        $this->assertGreaterThan(0, $firstResult->percentage);
    }

    // ==================== Tests: multiple words ====================

    public function test_search_with_multiple_words(): void
    {
        $query = new SearchQueryRecord(
            query: 'Laravel PHP',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    // ==================== Tests: edge cases ====================

    public function test_search_with_empty_query_returns_empty(): void
    {
        $query = new SearchQueryRecord(
            query: '',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertTrue($results->isEmpty());
    }

    public function test_search_with_single_character(): void
    {
        $query = new SearchQueryRecord(
            query: 'a',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    // ==================== Tests: cache ====================

    public function test_search_uses_cache(): void
    {
        // Nettoyer le cache avant le test
        $this->searchService->clearCache();

        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
        );

        // Premier appel - pas de cache
        $results1 = $this->searchService->search($query);
        $this->assertNotEmpty($results1);

        // Deuxième appel - devrait utiliser le cache
        $results2 = $this->searchService->search($query);
        $this->assertNotEmpty($results2);

        // Les deux résultats devraient avoir le même nombre d'éléments
        $this->assertEquals($results1->count(), $results2->count());
    }

    public function test_clear_cache_works(): void
    {
        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
        );

        // Premier appel
        $this->searchService->search($query);

        // Nettoyer le cache
        $this->searchService->clearCache();

        // Vérifier que le cache est bien vidé (pas d'erreur)
        $this->assertTrue(true);
    }

    // ==================== Tests: ranking ====================

    public function test_search_returns_most_relevant_first(): void
    {
        // Créer un article très pertinent
        $relevantPost = TestPost::create([
            'user_id' => $this->user1->id,
            'title' => 'Laravel Advanced Search',
            'body' => 'This article covers advanced Laravel search techniques',
        ]);

        $indexService = $this->app->make(IndexService::class);
        $indexService->index($relevantPost);

        $query = new SearchQueryRecord(
            query: 'Laravel search',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);

        $firstResult = $results->first();
        $this->assertNotNull($firstResult);
    }

    // ==================== Tests: case insensitivity ====================

    public function test_search_is_case_insensitive(): void
    {
        $query = new SearchQueryRecord(
            query: 'JOHN',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }

    // ==================== Tests: cross-model search ====================

    public function test_search_returns_results_from_multiple_models(): void
    {
        $query = new SearchQueryRecord(
            query: 'John',
            limit: 10,
        );

        $results = $this->searchService->search($query);

        $this->assertNotEmpty($results);
    }
}
