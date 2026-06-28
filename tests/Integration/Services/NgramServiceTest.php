<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class NgramServiceTest extends IntegrationTestCase
{
    private NgramInterface $service;

    private SearchConfig $config;

    private TextNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $configRepository = app(ConfigRepository::class);
        $this->config = new SearchConfig($configRepository);
        $this->normalizer = new TextNormalizerService($this->config);
        $this->service = new NgramService($this->config, $this->normalizer);
    }

    // ============================================================
    // TESTS DE LA MÉTHODE generate()
    // ============================================================

    public function test_generate_simple_word(): void
    {
        $result = $this->service->generate('test');

        $this->assertInstanceOf(SetCollection::class, $result);
        $this->assertNotEmpty($result->toArray());

        $ngrams = $result->toArray();
        $this->assertContains('te', $ngrams);
        $this->assertContains('es', $ngrams);
        $this->assertContains('st', $ngrams);
        $this->assertContains('tes', $ngrams);
        $this->assertContains('est', $ngrams);
        $this->assertContains('test', $ngrams);
    }

    public function test_generate_with_accents(): void
    {
        $result = $this->service->generate('café');

        $ngrams = $result->toArray();
        $this->assertContains('ca', $ngrams);
        $this->assertContains('af', $ngrams);
        $this->assertContains('fe', $ngrams);
        $this->assertContains('caf', $ngrams);
        $this->assertContains('afe', $ngrams);
        $this->assertContains('cafe', $ngrams);
    }

    public function test_generate_short_word(): void
    {
        $result = $this->service->generate('ab');

        $ngrams = $result->toArray();
        $this->assertContains('ab', $ngrams);
        $this->assertCount(1, $ngrams);
    }

    public function test_generate_word_shorter_than_min_ngram(): void
    {
        $result = $this->service->generate('a');

        $ngrams = $result->toArray();
        $this->assertEmpty($ngrams);
    }

    public function test_generate_empty_word(): void
    {
        $result = $this->service->generate('');

        $ngrams = $result->toArray();
        $this->assertEmpty($ngrams);
    }

    public function test_generate_with_uppercase(): void
    {
        $result = $this->service->generate('TEST');

        $ngrams = $result->toArray();
        $this->assertContains('te', $ngrams);
        $this->assertContains('es', $ngrams);
        $this->assertContains('st', $ngrams);
        $this->assertContains('tes', $ngrams);
        $this->assertContains('est', $ngrams);
        $this->assertContains('test', $ngrams);
    }

    public function test_generate_with_special_characters(): void
    {
        $result = $this->service->generate('hello@world');

        $ngrams = $result->toArray();
        $allGrams = implode('', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('el', $ngrams);
        $this->assertContains('ll', $ngrams);
        $this->assertContains('lo', $ngrams);
        $this->assertContains('wo', $ngrams);
        $this->assertContains('or', $ngrams);
        $this->assertContains('rl', $ngrams);
        $this->assertContains('ld', $ngrams);
        $this->assertStringNotContainsString('@', $allGrams);
    }

    public function test_generate_with_cyrillic_characters(): void
    {
        $result = $this->service->generate('привет');

        $ngrams = $result->toArray();
        $this->assertNotEmpty($ngrams);
        $this->assertContains('pr', $ngrams);
        $this->assertContains('ri', $ngrams);
    }

    // ============================================================
    // TESTS DE LA MÉTHODE generateFromWords()
    // ============================================================

    public function test_generate_from_words(): void
    {
        $words = ['test', 'hello'];
        $result = $this->service->generateFromWords($words);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('test', $result);
        $this->assertArrayHasKey('hello', $result);
        $this->assertNotEmpty($result['test']);
        $this->assertNotEmpty($result['hello']);
    }

    public function test_generate_from_words_empty(): void
    {
        $result = $this->service->generateFromWords([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_generate_from_words_with_duplicates(): void
    {
        $words = ['test', 'test', 'hello'];
        $result = $this->service->generateFromWords($words);

        $this->assertArrayHasKey('test', $result);
        $this->assertArrayHasKey('hello', $result);
        $this->assertCount(2, $result);
    }

    public function test_generate_from_words_with_accents(): void
    {
        $words = ['café', 'thé'];
        $result = $this->service->generateFromWords($words);

        $this->assertArrayHasKey('café', $result);
        $this->assertArrayHasKey('thé', $result);
        $this->assertContains('ca', $result['café']);
        $this->assertContains('th', $result['thé']);
    }

    // ============================================================
    // TESTS DE LA MÉTHODE generateFromText()
    // ============================================================

    public function test_generate_from_text(): void
    {
        $text = 'hello world';
        $result = $this->service->generateFromText($text);

        $this->assertInstanceOf(SetCollection::class, $result);
        $this->assertNotEmpty($result->toArray());

        $ngrams = $result->toArray();
        $this->assertContains('he', $ngrams);
        $this->assertContains('el', $ngrams);
        $this->assertContains('ll', $ngrams);
        $this->assertContains('lo', $ngrams);
        $this->assertContains('wo', $ngrams);
        $this->assertContains('or', $ngrams);
        $this->assertContains('rl', $ngrams);
        $this->assertContains('ld', $ngrams);
        $this->assertContains('wor', $ngrams);
        $this->assertContains('orl', $ngrams);
        $this->assertContains('rld', $ngrams);
    }

    public function test_generate_from_text_with_accents(): void
    {
        $text = 'café thé';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertContains('ca', $ngrams);
        $this->assertContains('af', $ngrams);
        $this->assertContains('fe', $ngrams);
        $this->assertContains('th', $ngrams);
        $this->assertContains('he', $ngrams);
    }

    public function test_generate_from_text_empty(): void
    {
        $text = '';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertEmpty($ngrams);
    }

    public function test_generate_from_text_with_punctuation(): void
    {
        $text = 'Hello, world!';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $allGrams = implode('', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('wo', $ngrams);
        $this->assertStringNotContainsString(',', $allGrams);
        $this->assertStringNotContainsString('!', $allGrams);
    }

    public function test_generate_from_text_with_stopwords(): void
    {
        $text = 'le chat et le chien';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertContains('le', $ngrams);
        $this->assertContains('ch', $ngrams);
        $this->assertContains('et', $ngrams);
    }

    public function test_generate_from_text_returns_unique_ngrams(): void
    {
        $text = 'test test test';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertCount(6, $ngrams);
    }

    public function test_generate_from_text_with_multiple_words(): void
    {
        $text = 'apple banana cherry';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertContains('ap', $ngrams);
        $this->assertContains('pp', $ngrams);
        $this->assertContains('pl', $ngrams);
        $this->assertContains('le', $ngrams);
        $this->assertContains('ba', $ngrams);
        $this->assertContains('an', $ngrams);
        $this->assertContains('na', $ngrams);
        $this->assertContains('ch', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('er', $ngrams);
        $this->assertContains('rr', $ngrams);
        $this->assertContains('ry', $ngrams);
    }

    // ============================================================
    // TESTS DE CONFIGURATION (SANS MOCK)
    // ============================================================

    public function test_generate_with_custom_config_min_ngram(): void
    {
        // Créer une config avec des valeurs personnalisées via le repository
        $configRepository = app(ConfigRepository::class);
        $configRepository->set('search.min_ngram_length', 3);
        $configRepository->set('search.max_ngram_length', 4);

        $config = new SearchConfig($configRepository);
        $service = new NgramService($config, $this->normalizer);

        $result = $service->generate('test');

        $ngrams = $result->toArray();
        $this->assertNotContains('te', $ngrams);
        $this->assertNotContains('es', $ngrams);
        $this->assertNotContains('st', $ngrams);
        $this->assertContains('tes', $ngrams);
        $this->assertContains('est', $ngrams);
        $this->assertContains('test', $ngrams);

        // Restaurer les valeurs par défaut
        $configRepository->set('search.min_ngram_length', 2);
        $configRepository->set('search.max_ngram_length', 4);
    }

    public function test_generate_with_custom_config_max_ngram(): void
    {
        // Créer une config avec des valeurs personnalisées via le repository
        $configRepository = app(ConfigRepository::class);
        $configRepository->set('search.min_ngram_length', 2);
        $configRepository->set('search.max_ngram_length', 3);

        $config = new SearchConfig($configRepository);
        $service = new NgramService($config, $this->normalizer);

        $result = $service->generate('test');

        $ngrams = $result->toArray();
        $this->assertContains('te', $ngrams);
        $this->assertContains('es', $ngrams);
        $this->assertContains('st', $ngrams);
        $this->assertContains('tes', $ngrams);
        $this->assertContains('est', $ngrams);
        $this->assertNotContains('test', $ngrams);

        // Restaurer les valeurs par défaut
        $configRepository->set('search.min_ngram_length', 2);
        $configRepository->set('search.max_ngram_length', 4);
    }

    public function test_generate_with_custom_gram_weights(): void
    {
        $configRepository = app(ConfigRepository::class);
        $configRepository->set('search.gram_weights', [
            2 => 0.5,
            3 => 0.8,
            4 => 1.0,
            'default' => 1.0,
        ]);

        $config = new SearchConfig($configRepository);
        $service = new NgramService($config, $this->normalizer);

        // Vérifier que les poids sont bien pris en compte
        $this->assertEquals(0.5, $config->getGramWeight(2));
        $this->assertEquals(0.8, $config->getGramWeight(3));
        $this->assertEquals(1.0, $config->getGramWeight(4));

        // Restaurer les valeurs par défaut
        $configRepository->set('search.gram_weights', [
            2 => 0.3,
            3 => 0.5,
            4 => 0.7,
            'default' => 1.0,
        ]);
    }

    // ============================================================
    // TESTS D'INTÉGRATION
    // ============================================================

    public function test_generate_from_text_with_realistic_content(): void
    {
        $text = 'Le site de la ville de Mont-Saint-Michel est magnifique !';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertNotEmpty($ngrams);
        $this->assertContains('le', $ngrams);
        $this->assertContains('si', $ngrams);
        $this->assertContains('vi', $ngrams);
        $this->assertContains('mo', $ngrams);
        $this->assertContains('sa', $ngrams);
        $this->assertContains('mi', $ngrams);
    }

    public function test_generate_from_text_with_multilingual_content(): void
    {
        $text = 'Bonjour Hello Hola Привет';
        $result = $this->service->generateFromText($text);

        $ngrams = $result->toArray();
        $this->assertContains('bo', $ngrams);
        $this->assertContains('he', $ngrams);
        $this->assertContains('ho', $ngrams);
        $this->assertContains('pr', $ngrams);
    }

    public function test_service_implements_interface(): void
    {
        $this->assertInstanceOf(NgramInterface::class, $this->service);
    }
}
