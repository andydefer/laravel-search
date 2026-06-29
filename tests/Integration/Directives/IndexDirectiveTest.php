<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelSearch\Contracts\Services\SearchableModelDiscoveryInterface;
use AndyDefer\LaravelSearch\Contracts\Services\SearchIndexInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Directives\IndexDirective;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestAddress;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;

final class IndexDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private SearchIndexInterface $searchIndex;

    private SearchableModelDiscoveryInterface $discovery;

    private TextNormalizerInterface $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);
        $this->searchIndex = $this->app->make(SearchIndexInterface::class);
        $this->discovery = $this->app->make(SearchableModelDiscoveryInterface::class);
        $this->normalizer = $this->app->make(TextNormalizerInterface::class);

        $configRepository = $this->app->make('config');
        $configRepository->set('search.searchable_paths', [
            __DIR__.'/../../Fixtures/Models',
        ]);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    private function normalizeOutput(string $output): string
    {
        // 1. Supprimer les codes ANSI
        $cleaned = $this->stripAnsi($output);

        // 2. Normaliser les accents
        $cleaned = $this->normalizer->normalize($cleaned);

        // 3. Supprimer les emojis
        $cleaned = preg_replace('/\p{Extended_Pictographic}/u', '', $cleaned);

        // 4. Normaliser les espaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    // ============================================================
    // TESTS: SIGNATURE
    // ============================================================

    public function test_get_signature_returns_correct_string(): void
    {
        $directive = $this->app->make(IndexDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('search:index', $signature);
        $this->assertStringContainsString('{morph-class?}', $signature);
        $this->assertStringContainsString('--batch=', $signature);
        $this->assertStringContainsString('--all', $signature);
        $this->assertStringContainsString('--sync', $signature);
        $this->assertStringContainsString('--reindex', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(IndexDirective::class);
        $description = $directive->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(IndexDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('search:reindex'));
        $this->assertTrue($aliases->contains('search:sync'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = $this->app->make(IndexDirective::class);
        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ============================================================
    // TESTS: VALIDATION
    // ============================================================

    public function test_execute_returns_invalid_argument_when_no_morph_class_and_no_all_flag(): void
    {
        $response = $this->service->run(IndexDirective::class, []);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Veuillez spécifier une classe ou utiliser --all', $response->output);
    }

    public function test_execute_returns_invalid_argument_when_class_does_not_exist(): void
    {
        $response = $this->service->run(IndexDirective::class, ['NonExistentClass']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString("La classe 'NonExistentClass' n'existe pas", $response->output);
    }

    public function test_execute_returns_invalid_argument_when_class_is_not_searchable(): void
    {
        $response = $this->service->run(IndexDirective::class, [\stdClass::class]);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString("n'implémente pas Searchable", $response->output);
    }

    // ============================================================
    // TESTS: INDEX SINGLE
    // ============================================================

    public function test_execute_index_single_model(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $response = $this->service->run(IndexDirective::class, [TestUser::class]);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('testuser', $output);
        $this->assertStringContainsString('indexation terminee 1 entites indexees', $output);

        $count = $this->searchIndex->getIndexedCount(TestUser::class);
        $this->assertEquals(1, $count);
    }

    public function test_execute_index_single_model_with_batch_size(): void
    {
        for ($i = 0; $i < 5; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'description' => "User {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->service->run(IndexDirective::class, [
            TestUser::class,
            '--batch=2',
        ]);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('testuser', $output);
        $this->assertStringContainsString('indexation terminee 5 entites indexees', $output);
    }

    // ============================================================
    // TESTS: INDEX ALL
    // ============================================================

    public function test_execute_index_all_models(): void
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

        $response = $this->service->run(IndexDirective::class, ['--all']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('decouverte de 3 modeles searchable', $output);
        $this->assertStringContainsString('total indexes 2', $output);
        $this->assertStringContainsString('toutes les indexations sont terminees avec succes', $output);

        $userCount = $this->searchIndex->getIndexedCount(TestUser::class);
        $productCount = $this->searchIndex->getIndexedCount(TestProduct::class);

        $this->assertEquals(1, $userCount);
        $this->assertEquals(1, $productCount);
    }

    public function test_execute_index_all_models_with_batch_size(): void
    {
        for ($i = 0; $i < 5; $i++) {
            TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'description' => "User {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->service->run(IndexDirective::class, ['--all', '--batch=2']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('total indexes 5', $output);
    }

    // ============================================================
    // TESTS: SYNC MODE
    // ============================================================

    public function test_execute_sync_single_model(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $response = $this->service->run(IndexDirective::class, [
            TestUser::class,
            '--sync',
        ]);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('mode synchronisation', $output);
        $this->assertStringContainsString('indexes 1', $output);
        $this->assertStringContainsString('supprimes 0', $output);
        $this->assertStringContainsString('total 1', $output);
    }

    public function test_execute_sync_all_models(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $response = $this->service->run(IndexDirective::class, ['--all', '--sync']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('resume global', $output);
        $this->assertStringContainsString('indexes 1', $output);
        $this->assertStringContainsString('supprimes 0', $output);
        $this->assertStringContainsString('total 1', $output);
    }

    // ============================================================
    // TESTS: REINDEX MODE
    // ============================================================

    public function test_execute_reindex_single_model(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->searchIndex->indexAll(TestUser::class);

        $user->update(['name' => 'John Smith']);

        $response = $this->service->run(IndexDirective::class, [
            TestUser::class,
            '--reindex',
        ]);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('mode reindexation', $output);
        $this->assertStringContainsString('reindexation terminee 1 entites indexees', $output);
    }

    public function test_execute_reindex_all_models(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        $this->searchIndex->indexAll(TestUser::class);

        $user->update(['name' => 'John Smith']);

        $response = $this->service->run(IndexDirective::class, ['--all', '--reindex']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('total indexes 1', $output);
        $this->assertStringContainsString('toutes les indexations sont terminees avec succes', $output);
    }

    // ============================================================
    // TESTS: EDGE CASES
    // ============================================================

    public function test_execute_returns_success_when_no_searchable_models_found(): void
    {
        $configRepository = $this->app->make('config');
        $configRepository->set('search.searchable_paths', [
            __DIR__.'/empty',
        ]);

        $response = $this->service->run(IndexDirective::class, ['--all']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Aucun modèle Searchable trouvé', $response->output);
    }

    public function test_execute_with_models_that_have_no_entities_to_index(): void
    {
        $response = $this->service->run(IndexDirective::class, [TestUser::class]);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('indexation terminee 0 entites indexees', $output);
    }

    public function test_execute_with_multiple_models_all(): void
    {
        TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        TestAddress::create([
            'user_id' => 1,
            'street' => '123 Main St',
            'city' => 'Paris',
            'country' => 'France',
            'postal_code' => '75001',
            'is_active' => true,
        ]);

        TestProduct::create([
            'name' => 'MacBook Pro',
            'reference' => 'MBP-2024',
            'description' => 'Ordinateur portable',
            'is_published' => true,
        ]);

        $response = $this->service->run(IndexDirective::class, ['--all']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('total indexes 3', $output);
        $this->assertStringContainsString('toutes les indexations sont terminees avec succes', $output);
    }

    public function test_execute_sync_with_multiple_models_all(): void
    {
        TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        TestProduct::create([
            'name' => 'MacBook Pro',
            'reference' => 'MBP-2024',
            'description' => 'Ordinateur portable',
            'is_published' => true,
        ]);

        $response = $this->service->run(IndexDirective::class, ['--all', '--sync']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('resume global', $output);
        $this->assertStringContainsString('indexes 2', $output);
        $this->assertStringContainsString('supprimes 0', $output);
        $this->assertStringContainsString('total 2', $output);
    }

    public function test_execute_sync_with_multiple_models_all_shows_correct_counts(): void
    {
        TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Software Developer',
            'is_active' => true,
        ]);

        TestUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'description' => 'Designer',
            'is_active' => false,
        ]);

        $response = $this->service->run(IndexDirective::class, ['--all', '--sync']);
        $output = $this->normalizeOutput($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('indexes 1', $output);
        $this->assertStringContainsString('supprimes 0', $output);
        $this->assertStringContainsString('ignores 1', $output);
        $this->assertStringContainsString('total 2', $output);
    }
}
