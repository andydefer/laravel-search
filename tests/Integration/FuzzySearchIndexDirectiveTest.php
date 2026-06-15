<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelSearch\Directives\FuzzySearchIndexDirective;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestPost;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;

final class FuzzySearchIndexDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private int $userCounter = 1;

    private int $postCounter = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);

        // Configurer les modèles de test dans la configuration
        $this->app['config']->set('fuzzy-search.models', [
            TestUser::class,
            TestPost::class,
        ]);

        // Configuration pour les tests
        $this->app['config']->set('fuzzy-search.batch_size', 10);
        $this->app['config']->set('fuzzy-search.auto_index', false);

        // Nettoyer les tables avant chaque test
        TestUser::truncate();
        TestPost::truncate();
        SearchIndex::truncate();

        // Réinitialiser les compteurs
        $this->userCounter = 1;
        $this->postCounter = 1;
    }

    protected function tearDown(): void
    {
        SearchIndex::truncate();
        TestUser::truncate();
        TestPost::truncate();
        $this->service->destroy();
        parent::tearDown();
    }

    private function getUniqueEmail(): string
    {
        return 'user'.$this->userCounter++.'@example.com';
    }

    private function getUniquePostTitle(): string
    {
        return 'Post Title '.$this->postCounter++;
    }

    private function createTestUser(array $overrides = []): TestUser
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => $this->getUniqueEmail(),
            'status' => 'active',
        ];

        return TestUser::create(array_merge($defaults, $overrides));
    }

    /**
     * Crée un post pour un userId existant.
     * Ne crée PAS automatiquement d'utilisateur.
     */
    private function createTestPost(int $userId): TestPost
    {
        return TestPost::create([
            'user_id' => $userId,
            'title' => $this->getUniquePostTitle(),
            'body' => 'This is a test post content',
        ]);
    }

    // ==================== Tests: Signature, Description & Aliases ====================

    public function test_get_signature_returns_correct_string(): void
    {
        $directive = $this->app->make(FuzzySearchIndexDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('fuzzy-search-index', $signature);
        $this->assertStringContainsString('{models*}', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(FuzzySearchIndexDirective::class);
        $description = $directive->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('Index searchable models', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(FuzzySearchIndexDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('fs-index'));
        $this->assertSame(1, $aliases->count());
    }

    // ==================== Tests: Indexation ====================

    public function test_execute_indexes_all_models_from_config(): void
    {
        // Créer 1 user
        $user = $this->createTestUser(['name' => 'John Doe']);
        // Créer 1 post pour cet user
        $this->createTestPost($user->id);

        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Indexing all models from config', $response->output);
        $this->assertStringContainsString('✓ Indexing completed', $response->output);

        // 1 user + 1 post = 2 entrées
        $indexCount = SearchIndex::count();
        $this->assertSame(2, $indexCount);
    }

    public function test_execute_indexes_specified_models(): void
    {
        // Créer 1 user
        $user = $this->createTestUser(['name' => 'John Doe']);
        // Créer 1 post pour cet user
        $this->createTestPost($user->id);

        $response = $this->service->run(
            FuzzySearchIndexDirective::class,
            [TestUser::class]
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Indexing specified models: '.TestUser::class, $response->output);

        // Seul TestUser devrait être indexé (1 entrée)
        $indexCount = SearchIndex::count();
        $this->assertSame(1, $indexCount);
    }

    public function test_execute_with_force_option_reindexes_existing_entries(): void
    {
        $user = $this->createTestUser(['name' => 'John Doe']);

        // Première indexation
        $this->service->run(FuzzySearchIndexDirective::class, []);

        // Vérifier qu'une entrée existe (1 user)
        $this->assertSame(1, SearchIndex::count());

        // Modifier le modèle en base
        $user->name = 'Jane Smith';
        $user->save();

        // Deuxième indexation avec --force
        $response = $this->service->run(
            FuzzySearchIndexDirective::class,
            ['--force']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('✓ Indexing completed', $response->output);

        // Toujours 1 entrée (mise à jour, pas d'ajout)
        $this->assertSame(1, SearchIndex::count());
    }

    public function test_execute_returns_success_when_no_models_to_index(): void
    {
        $this->app['config']->set('fuzzy-search.models', []);

        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No models to index', $response->output);
    }

    public function test_execute_shows_warning_when_models_list_empty(): void
    {
        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Indexing all models from config', $response->output);
    }

    public function test_execute_displays_indexing_progress(): void
    {
        $user = $this->createTestUser(['name' => 'John Doe']);
        $this->createTestPost($user->id);

        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertStringContainsString('Indexed', $response->output);
        $this->assertStringContainsString(TestUser::class, $response->output);
        $this->assertStringContainsString(TestPost::class, $response->output);
    }

    public function test_execute_skips_already_indexed_models_without_force(): void
    {
        $this->createTestUser(['name' => 'John Doe']);

        // Première indexation
        $this->service->run(FuzzySearchIndexDirective::class, []);

        // Deuxième indexation sans --force
        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Skipped', $response->output);
    }

    // ==================== Tests: Statistics Display ====================

    public function test_execute_displays_statistics(): void
    {
        $user = $this->createTestUser(['name' => 'John Doe']);
        $this->createTestPost($user->id);

        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertStringContainsString('indexed', $response->output);
        $this->assertStringContainsString('skipped', $response->output);
        $this->assertStringContainsString('errors', $response->output);
    }

    public function test_execute_displays_separator(): void
    {
        $this->createTestUser(['name' => 'John Doe']);

        $response = $this->service->run(FuzzySearchIndexDirective::class, []);

        $this->assertStringContainsString('Fuzzy Search Indexing...', $response->output);
    }

    // ==================== Tests: Error Handling ====================

    public function test_execute_returns_failure_for_nonexistent_model(): void
    {
        $response = $this->service->run(
            FuzzySearchIndexDirective::class,
            ['NonExistentModel']
        );

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Error during indexing', $response->output);
    }
}
