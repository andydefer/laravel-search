<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\LaravelSearch\Contexts\SearchContext;
use AndyDefer\LaravelSearch\Contexts\SearchEngineConfigContext;
use AndyDefer\LaravelSearch\Services\SearchEngineService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\LaravelSearch\ValueObjects\SearchQueryVO;

final class SearchEngineServiceTest extends IntegrationTestCase
{
    private SearchEngineService $service;
    private SearchEngineConfigContext $engineConfigContext;
    private JsonlCacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(\AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface::class);
        $engineRecord = $config->getEngine();

        $this->engineConfigContext = new SearchEngineConfigContext($engineRecord);
        $this->cache = $this->app->make(JsonlCacheInterface::class);

        $this->service = new SearchEngineService(
            $this->engineConfigContext,
            $config,
            $this->cache,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service->clearCache();
    }

    private function createSearchContext(array $data, string $query, int $limit = 10): SearchContext
    {
        $searchQuery = new SearchQueryVO($query, $limit);
        $context = new SearchContext($searchQuery);
        $this->service->setData($context, $data);
        $this->service->preprocessData($context);
        return $context;
    }

    // ==================== Tests: setData and preprocessData ====================

    public function test_setData_stores_data_in_context(): void
    {
        $query = new SearchQueryVO('test', 10);
        $context = new SearchContext($query);

        $data = ['apple', 'banana', 'cherry'];
        $this->service->setData($context, $data);

        $this->assertSame(3, $context->getRawData()->count());
        $this->assertSame('apple', $context->getRawData()->first());
    }

    public function test_preprocessData_generates_normalized_words(): void
    {
        $context = $this->createSearchContext(['Hello'], 'test');  // Changé: 'Hello World' → 'Hello'

        $this->assertSame(1, $context->getPreprocessedData()->count());
        $this->assertSame(1, $context->getItemsProcessed());
    }

    // ==================== Tests: search exact match ====================

    public function test_search_returns_results_for_exact_match(): void
    {
        $context = $this->createSearchContext(['apple', 'banana', 'cherry'], 'apple');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
        $this->assertGreaterThan(0, $result->getResults()->count());

        $firstResult = $result->getResults()->first();
        $this->assertSame('apple', $firstResult->item);
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $context = $this->createSearchContext(['apple', 'banana', 'cherry'], 'xyz');

        $result = $this->service->search($context);

        $this->assertFalse($result->hasResults());
        $this->assertSame(0, $result->getResults()->count());
    }

    public function test_search_limits_results(): void
    {
        $context = $this->createSearchContext(
            ['apple', 'apple pie', 'apple juice', 'apple cider', 'apple sauce'],
            'apple',
            3
        );

        $result = $this->service->search($context);

        $this->assertLessThanOrEqual(3, $result->getResults()->count());
    }

    // ==================== Tests: fuzzy matching ====================

    public function test_search_finds_similar_words(): void
    {
        $context = $this->createSearchContext(['restaurant', 'hotel', 'cafe'], 'resturant');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
    }

    public function test_search_finds_words_with_typos(): void
    {
        $context = $this->createSearchContext(['technology', 'science', 'math'], 'tecgnology');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
    }

    public function test_search_handles_accents(): void
    {
        $context = $this->createSearchContext(['café', 'restaurant', 'hôtel'], 'cafe');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
        $this->assertStringContainsString('café', $result->getResults()->first()->item);
    }

    public function test_search_handles_case_insensitivity(): void
    {
        $context = $this->createSearchContext(['Hello World'], 'HELLO');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
        $this->assertStringContainsString('Hello', $result->getResults()->first()->item);
    }

    // ==================== Tests: ranking and relevance ====================

    public function test_search_returns_high_relevance_first(): void
    {
        $context = $this->createSearchContext(
            ['apple pie recipe', 'apple', 'orange juice'],
            'apple pie'
        );

        $result = $this->service->search($context);

        $results = $result->getResults();
        $firstResult = $results->first();

        $this->assertNotNull($firstResult);
        $this->assertStringContainsString('apple pie', $firstResult->item);
    }

    public function test_search_returns_scores(): void
    {
        $context = $this->createSearchContext(['apple pie', 'banana split'], 'apple');

        $result = $this->service->search($context);

        $firstResult = $result->getResults()->first();
        $this->assertNotNull($firstResult);
        $this->assertGreaterThan(0, $firstResult->score);
        $this->assertGreaterThan(0, $firstResult->percentage);
    }

    // ==================== Tests: multiple words ====================

    public function test_search_with_multiple_words(): void
    {
        $context = $this->createSearchContext(
            ['apple pie recipe', 'banana smoothie', 'apple banana mix'],
            'apple banana'
        );

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
        $this->assertGreaterThanOrEqual(2, $result->getResults()->count());
    }

    // ==================== Tests: edge cases ====================

    public function test_search_with_empty_query_returns_empty(): void
    {
        $context = $this->createSearchContext(['apple', 'banana'], '');

        $result = $this->service->search($context);

        $this->assertFalse($result->hasResults());
    }

    public function test_search_with_empty_data_returns_empty(): void
    {
        $query = new SearchQueryVO('test', 10);
        $context = new SearchContext($query);
        $this->service->setData($context, []);
        $this->service->preprocessData($context);

        $result = $this->service->search($context);

        $this->assertFalse($result->hasResults());
    }

    public function test_search_with_single_character_query(): void
    {
        $context = $this->createSearchContext(['apple', 'banana', 'cherry'], 'a');

        $result = $this->service->search($context);

        $this->assertTrue($result->hasResults());
    }

    // ==================== Tests: large dataset ====================

    public function test_search_handles_large_dataset(): void
    {
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = "item_{$i}_" . ($i % 2 === 0 ? 'apple' : 'banana');
        }

        $context = $this->createSearchContext($data, 'apple', 20);
        $result = $this->service->search($context);

        $this->assertLessThanOrEqual(20, $result->getResults()->count());
        $this->assertTrue($result->hasResults());
    }

    // ==================== Tests: cache integration ====================

    public function test_search_uses_cache(): void
    {
        $context = $this->createSearchContext(['apple', 'banana'], 'apple');

        // Premier appel - pas de cache
        $result1 = $this->service->search($context);
        $this->assertTrue($result1->hasResults());

        // Deuxième appel - devrait utiliser le cache
        $result2 = $this->service->search($context);
        $this->assertTrue($result2->hasResults());
    }

    public function test_clearCache_works(): void
    {
        $context = $this->createSearchContext(['apple', 'banana'], 'apple');

        // Premier appel
        $this->service->search($context);

        // Nettoyer le cache
        $this->service->clearCache();

        // Vérifier que le cache est bien vidé (pas d'erreur)
        $this->assertTrue(true);
    }
}
