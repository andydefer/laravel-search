<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class NgramServiceTest extends IntegrationTestCase
{
    private NgramService $service;

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

    public function test_generate_empty_word(): void
    {
        $result = $this->service->generate('');

        $ngrams = $result->toArray();
        $this->assertEmpty($ngrams);
    }

    public function test_generate_from_words(): void
    {
        $words = ['test', 'hello'];
        $result = $this->service->generateFromWords($words);

        $this->assertArrayHasKey('test', $result);
        $this->assertArrayHasKey('hello', $result);
        $this->assertNotEmpty($result['test']);
        $this->assertNotEmpty($result['hello']);
    }

    public function test_generate_from_text(): void
    {
        $text = 'hello world';
        $result = $this->service->generateFromText($text);

        $this->assertInstanceOf(SetCollection::class, $result);
        $this->assertNotEmpty($result->toArray());

        $ngrams = $result->toArray();
        $this->assertContains('he', $ngrams);
        $this->assertContains('wo', $ngrams);
        $this->assertContains('wor', $ngrams);
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
}
