<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\MatchResultCollection;
use AndyDefer\LaravelSearch\Collections\QueryWordsCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\QueryProcessorService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class QueryProcessorServiceTest extends IntegrationTestCase
{
    private QueryProcessorService $service;

    private SearchConfig $config;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    protected function setUp(): void
    {
        parent::setUp();

        $configRepository = app(ConfigRepository::class);

        $configRepository->set('search.gram_weights', [
            2 => 0.3,
            3 => 0.5,
            4 => 0.7,
            'default' => 1.0,
        ]);

        $configRepository->set('search.stop_words', ['le', 'la', 'les', 'un', 'une', 'des', 'et', 'ou']);

        $this->config = new SearchConfig($configRepository);
        $this->normalizer = $this->app->make(TextNormalizerService::class);
        $this->ngramService = $this->app->make(NgramService::class);
        $this->service = new QueryProcessorService($this->config, $this->normalizer, $this->ngramService);
    }

    // ============================================================
    // TESTS DE PROCESS
    // ============================================================

    public function test_process_query(): void
    {
        $query = StringVO::from('hello world');
        $result = $this->service->process($query);

        $this->assertInstanceOf(QueryWordsCollection::class, $result);
        $this->assertEquals(2, $result->count());

        $words = $result->toArray();
        $this->assertEquals('hello', $words[0]->original->getValue());
        $this->assertEquals('world', $words[1]->original->getValue());
    }

    public function test_process_single_word(): void
    {
        $query = StringVO::from('test');
        $result = $this->service->process($query);

        $this->assertEquals(1, $result->count());

        $word = $result->first();
        $this->assertInstanceOf(QueryWordRecord::class, $word);
        $this->assertEquals('test', $word->original->getValue());
        $this->assertEquals('test', $word->normalized->getValue());
        $this->assertInstanceOf(StringTypedCollection::class, $word->ngrams);
    }

    public function test_process_empty_query(): void
    {
        $query = StringVO::from('');
        $result = $this->service->process($query);

        $this->assertInstanceOf(QueryWordsCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_process_with_accents(): void
    {
        $query = StringVO::from('café thé');
        $result = $this->service->process($query);

        $this->assertEquals(2, $result->count());

        $words = $result->toArray();
        $this->assertEquals('cafe', $words[0]->normalized->getValue());
        $this->assertEquals('the', $words[1]->normalized->getValue());
    }

    public function test_process_with_stopwords(): void
    {
        $query = StringVO::from('le chat et le chien');
        $result = $this->service->process($query);

        $this->assertEquals(2, $result->count());

        $words = $result->toArray();
        $this->assertEquals('chat', $words[0]->original->getValue());
        $this->assertEquals('chien', $words[1]->original->getValue());
    }

    public function test_process_with_hyphens(): void
    {
        $query = StringVO::from('Jean-Pierre');
        $result = $this->service->process($query);

        $this->assertEquals(2, $result->count());

        $words = $result->toArray();
        $this->assertEquals('jean', $words[0]->original->getValue());
        $this->assertEquals('pierre', $words[1]->original->getValue());
    }

    // ============================================================
    // TESTS DE calculateMaxScore
    // ============================================================

    public function test_calculate_max_score_for_short_word(): void
    {
        $result = $this->service->calculateMaxScore('ab');

        $this->assertInstanceOf(FloatVO::class, $result);
        $this->assertEquals(1.0, $result->getValue());
    }

    public function test_calculate_max_score_for_medium_word(): void
    {
        $result = $this->service->calculateMaxScore('test');

        $this->assertEquals(1.3, $result->getValue());
    }

    public function test_calculate_max_score_for_long_word(): void
    {
        $result = $this->service->calculateMaxScore('testing');

        $this->assertEquals(2.8, $result->getValue());
    }

    public function test_calculate_max_score_for_very_long_word(): void
    {
        $result = $this->service->calculateMaxScore('ordinateur');

        $this->assertEquals(4.3, $result->getValue());
    }

    public function test_calculate_max_score_never_less_than_one(): void
    {
        $result = $this->service->calculateMaxScore('');
        $this->assertEquals(1.0, $result->getValue());

        $result = $this->service->calculateMaxScore('a');
        $this->assertEquals(1.0, $result->getValue());
    }

    // ============================================================
    // TESTS DE COMPUTE SCORE
    // ============================================================

    public function test_compute_score_exact_match(): void
    {
        $ngrams = $this->ngramService->generate('test')->toArray();
        $maxScore = $this->service->calculateMaxScore('test');

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('test'),
            'normalized' => StringVO::from('test'),
            'ngrams' => StringTypedCollection::from($ngrams),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'test',
            'ngrams' => StringTypedCollection::from($ngrams),
            'max_score' => $maxScore,
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->count());

        $match = $result->first();
        $this->assertEquals(100.0, $match->percentage->getValue());
    }

    public function test_compute_score_partial_match(): void
    {
        $queryNgrams = $this->ngramService->generate('test')->toArray();
        $itemNgrams = $this->ngramService->generate('testing')->toArray();

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('test'),
            'normalized' => StringVO::from('test'),
            'ngrams' => StringTypedCollection::from($queryNgrams),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'testing',
            'ngrams' => StringTypedCollection::from($itemNgrams),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->count());

        $match = $result->first();
        $this->assertGreaterThan(0, $match->score->getValue());
        $this->assertGreaterThan(0, $match->percentage->getValue());
        $this->assertLessThan(100, $match->percentage->getValue());
    }

    public function test_compute_score_no_match(): void
    {
        $queryNgrams = $this->ngramService->generate('test')->toArray();
        $itemNgrams = $this->ngramService->generate('xyz')->toArray();

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('test'),
            'normalized' => StringVO::from('test'),
            'ngrams' => StringTypedCollection::from($queryNgrams),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'xyz',
            'ngrams' => StringTypedCollection::from($itemNgrams),
            'max_score' => FloatVO::from(1.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNull($result);
    }

    public function test_compute_score_multiple_words(): void
    {
        $helloNgrams = $this->ngramService->generate('hello')->toArray();
        $worldNgrams = $this->ngramService->generate('world')->toArray();

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('hello'),
            'normalized' => StringVO::from('hello'),
            'ngrams' => StringTypedCollection::from($helloNgrams),
        ]));
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('world'),
            'normalized' => StringVO::from('world'),
            'ngrams' => StringTypedCollection::from($worldNgrams),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'hello',
            'ngrams' => StringTypedCollection::from($helloNgrams),
            'max_score' => FloatVO::from(2.0),
        ]));
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'world',
            'ngrams' => StringTypedCollection::from($worldNgrams),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertEquals(2, $result->count());
    }

    public function test_compute_score_with_levenshtein_penalty(): void
    {
        $queryNgrams = $this->ngramService->generate('test')->toArray();
        $itemNgrams = $this->ngramService->generate('tst')->toArray();

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('test'),
            'normalized' => StringVO::from('test'),
            'ngrams' => StringTypedCollection::from($queryNgrams),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'tst',
            'ngrams' => StringTypedCollection::from($itemNgrams),
            'max_score' => FloatVO::from(1.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->count());

        $match = $result->first();
        $this->assertGreaterThan(0, $match->score->getValue());
        $this->assertLessThan(100, $match->percentage->getValue());
    }

    public function test_compute_score_with_empty_query_words(): void
    {
        $queryWords = new QueryWordsCollection;
        $itemWords = new ItemWordsCollection;

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNull($result);
    }

    public function test_compute_score_with_empty_item_words(): void
    {
        $ngrams = $this->ngramService->generate('test')->toArray();

        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('test'),
            'normalized' => StringVO::from('test'),
            'ngrams' => StringTypedCollection::from($ngrams),
        ]));

        $itemWords = new ItemWordsCollection;

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNull($result);
    }

    // ============================================================
    // TESTS DE SORT
    // ============================================================

    public function test_sort_results(): void
    {
        $results = new MatchResultCollection;
        $results->add(MatchResultRecord::from([
            'score' => FloatVO::from(0.5),
            'max_possible' => FloatVO::from(1.0),
            'percentage' => FloatVO::from(50.0),
        ]));
        $results->add(MatchResultRecord::from([
            'score' => FloatVO::from(0.8),
            'max_possible' => FloatVO::from(1.0),
            'percentage' => FloatVO::from(80.0),
        ]));
        $results->add(MatchResultRecord::from([
            'score' => FloatVO::from(0.3),
            'max_possible' => FloatVO::from(1.0),
            'percentage' => FloatVO::from(30.0),
        ]));

        $sorted = $this->service->sortResults($results);

        $this->assertEquals(3, $sorted->count());

        $items = $sorted->toArray();
        $this->assertEquals(80.0, $items[0]->percentage->getValue());
        $this->assertEquals(50.0, $items[1]->percentage->getValue());
        $this->assertEquals(30.0, $items[2]->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : NOMS DE PERSONNES
    // ============================================================

    public function test_scoring_names_exact_match(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('jean'),
            'normalized' => StringVO::from('jean'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean')->toArray()),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'jean',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);
        $this->assertNotNull($result);
        $this->assertEquals(100.0, $result->first()->percentage->getValue());
    }

    public function test_scoring_names_with_typo(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('jean'),
            'normalized' => StringVO::from('jean'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean')->toArray()),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'jean',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertEquals(100.0, $result->first()->percentage->getValue());
    }

    public function test_scoring_names_with_accents(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('rené'),
            'normalized' => StringVO::from('rene'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('rene')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'rené',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('rene')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'rene',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('rene')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan(90, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(90, $result2->first()->percentage->getValue());
    }

    public function test_scoring_names_compound(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('jean-pierre'),
            'normalized' => StringVO::from('jean-pierre'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean-pierre')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'jean-pierre',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean-pierre')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'jean',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('jean')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan($result2->first()->percentage->getValue(), $result1->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : NOMS DE PRODUITS
    // ============================================================

    public function test_scoring_products_exact_match(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('iphone'),
            'normalized' => StringVO::from('iphone'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'iphone',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);
        $this->assertNotNull($result);
        $this->assertEquals(100.0, $result->first()->percentage->getValue());
    }

    public function test_scoring_products_typo_variation(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('iphone'),
            'normalized' => StringVO::from('iphone'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'iphone',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);

        $this->assertNotNull($result);
        $this->assertGreaterThan(80, $result->first()->percentage->getValue());
    }

    public function test_scoring_products_model_numbers(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('iphone'),
            'normalized' => StringVO::from('iphone'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
        ]));
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('15'),
            'normalized' => StringVO::from('15'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('15')->toArray()),
        ]));

        $itemWords = new ItemWordsCollection;
        $itemWords->add(ItemWordRecord::from([
            'normalized' => 'iphone',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('iphone')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));
        $itemWords->add(ItemWordRecord::from([
            'normalized' => '15',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('15')->toArray()),
            'max_score' => FloatVO::from(1.0),
        ]));

        $result = $this->service->computeScore($queryWords, $itemWords);
        $this->assertNotNull($result);
        $this->assertEquals(2, $result->count());

        $scores = array_map(fn ($r) => $r->percentage->getValue(), $result->toArray());
        $this->assertGreaterThan(80, $scores[0]);
        $this->assertGreaterThan(80, $scores[1]);
    }

    public function test_scoring_products_brand_variations(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('macbook'),
            'normalized' => StringVO::from('macbook'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('macbook')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'macbook',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('macbook')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'mac book',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('macbook')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords3 = new ItemWordsCollection;
        $itemWords3->add(ItemWordRecord::from([
            'normalized' => 'macbook pro',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('macbookpro')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);
        $result3 = $this->service->computeScore($queryWords, $itemWords3);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);

        $this->assertGreaterThan(70, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(70, $result2->first()->percentage->getValue());
        $this->assertGreaterThan(70, $result3->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : VILLES ET LOCALISATIONS
    // ============================================================

    public function test_scoring_cities_with_accents(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('montreal'),
            'normalized' => StringVO::from('montreal'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('montreal')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'montréal',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('montreal')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'montreal',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('montreal')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan(90, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(90, $result2->first()->percentage->getValue());
    }

    public function test_scoring_cities_compound_names(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('saint denis'),
            'normalized' => StringVO::from('saint denis'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('saintdenis')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'saint-denis',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('saint-denis')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'saint denis',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('saintdenis')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan(80, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(80, $result2->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : ENTREPRISES
    // ============================================================

    public function test_scoring_companies_variations(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('apple'),
            'normalized' => StringVO::from('apple'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('apple')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'apple inc',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('appleinc')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'apple store',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('applestore')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords3 = new ItemWordsCollection;
        $itemWords3->add(ItemWordRecord::from([
            'normalized' => 'aple',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('aple')->toArray()),
            'max_score' => FloatVO::from(1.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);
        $result3 = $this->service->computeScore($queryWords, $itemWords3);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);

        $this->assertGreaterThan(80, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(80, $result2->first()->percentage->getValue());
        $this->assertLessThan($result1->first()->percentage->getValue(), $result3->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : TITRES DE FILMS
    // ============================================================

    public function test_scoring_movie_titles(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('inception'),
            'normalized' => StringVO::from('inception'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('inception')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'inception',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('inception')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'incepion',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('incepion')->toArray()),
            'max_score' => FloatVO::from(2.5),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertEquals(100.0, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(80, $result2->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : EMAILS
    // ============================================================

    public function test_scoring_emails(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('contact'),
            'normalized' => StringVO::from('contact'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('contact')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'contact example com',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('contactexamplecom')->toArray()),
            'max_score' => FloatVO::from(3.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'contato example com',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('contatoexamplecom')->toArray()),
            'max_score' => FloatVO::from(3.0),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan(80, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(65, $result2->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : ADRESSES
    // ============================================================

    public function test_scoring_addresses(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('rue de la paix'),
            'normalized' => StringVO::from('rue de la paix'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('ruedelapaix')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'rue de la paix',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('ruedelapaix')->toArray()),
            'max_score' => FloatVO::from(3.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'rue paix',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('ruepaix')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertGreaterThan(80, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(50, $result2->first()->percentage->getValue());
    }

    // ============================================================
    // CAS RÉELS : CATÉGORIES PRODUITS
    // ============================================================

    public function test_scoring_clothing_categories(): void
    {
        $queryWords = new QueryWordsCollection;
        $queryWords->add(QueryWordRecord::from([
            'original' => StringVO::from('chemise'),
            'normalized' => StringVO::from('chemise'),
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('chemise')->toArray()),
        ]));

        $itemWords1 = new ItemWordsCollection;
        $itemWords1->add(ItemWordRecord::from([
            'normalized' => 'chemise',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('chemise')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords2 = new ItemWordsCollection;
        $itemWords2->add(ItemWordRecord::from([
            'normalized' => 'chemises',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('chemises')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $itemWords3 = new ItemWordsCollection;
        $itemWords3->add(ItemWordRecord::from([
            'normalized' => 'chemisier',
            'ngrams' => StringTypedCollection::from($this->ngramService->generate('chemisier')->toArray()),
            'max_score' => FloatVO::from(2.0),
        ]));

        $result1 = $this->service->computeScore($queryWords, $itemWords1);
        $result2 = $this->service->computeScore($queryWords, $itemWords2);
        $result3 = $this->service->computeScore($queryWords, $itemWords3);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);

        $this->assertEquals(100.0, $result1->first()->percentage->getValue());
        $this->assertGreaterThan(90, $result2->first()->percentage->getValue());
        $this->assertGreaterThan(70, $result3->first()->percentage->getValue());
    }
}
