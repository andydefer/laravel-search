<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Services\NgramService;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class WordVectorParserServiceTest extends IntegrationTestCase
{
    private WordVectorParserService $service;

    private TextNormalizerService $normalizer;

    private NgramService $ngramService;

    protected function setUp(): void
    {
        parent::setUp();

        $configRepository = app(ConfigRepository::class);
        $config = new SearchConfig($configRepository);
        $this->normalizer = new TextNormalizerService($config);
        $this->ngramService = new NgramService($config, $this->normalizer);
        $this->service = new WordVectorParserService($this->normalizer, $this->ngramService);
    }

    public function test_parse_single_word(): void
    {
        $words = ['test'];
        $result = $this->service->parse($words);

        $this->assertInstanceOf(WordVectorCollection::class, $result);
        $this->assertEquals(1, $result->count());

        $record = $result->first();
        $this->assertEquals('test', $record->word);
        $this->assertNotEmpty($record->metaphone);
        $this->assertNotEmpty($record->unique_letters->toArray());
        $this->assertNotEmpty($record->bigrams->toArray());
        $this->assertNotEmpty($record->metaphone_bigrams->toArray());
    }

    public function test_parse_multiple_words(): void
    {
        $words = ['test', 'hello'];
        $result = $this->service->parse($words);

        $this->assertEquals(2, $result->count());

        $records = $result->toArray();
        $this->assertEquals('test', $records[0]->word);
        $this->assertEquals('hello', $records[1]->word);
    }

    public function test_parse_empty_array(): void
    {
        $result = $this->service->parse([]);

        $this->assertInstanceOf(WordVectorCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_parse_with_accents(): void
    {
        $words = ['café'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('café', $record->word);
        $this->assertNotEmpty($record->metaphone);
        $this->assertContains('c', $record->unique_letters->toArray());
        $this->assertContains('a', $record->unique_letters->toArray());
        $this->assertContains('f', $record->unique_letters->toArray());
        $this->assertContains('e', $record->unique_letters->toArray());
    }

    public function test_parse_with_hyphen(): void
    {
        $words = ['jean-pierre'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('jean-pierre', $record->word);
        $this->assertNotEmpty($record->metaphone);
        $this->assertStringContainsString('jean', $record->word);
    }

    public function test_parse_uppercase_word(): void
    {
        $words = ['TEST'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('TEST', $record->word);
        $this->assertNotEmpty($record->metaphone);
    }

    public function test_parse_with_special_characters(): void
    {
        $words = ['hello@world'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('hello@world', $record->word);
        $this->assertNotEmpty($record->metaphone);
        $this->assertContains('h', $record->unique_letters->toArray());
        $this->assertContains('e', $record->unique_letters->toArray());
        $this->assertContains('l', $record->unique_letters->toArray());
        $this->assertContains('o', $record->unique_letters->toArray());
        $this->assertContains('w', $record->unique_letters->toArray());
        $this->assertContains('r', $record->unique_letters->toArray());
        $this->assertContains('d', $record->unique_letters->toArray());
    }

    public function test_parse_with_numbers(): void
    {
        $words = ['test123'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('test123', $record->word);
        $this->assertNotEmpty($record->metaphone);
        $this->assertContains('t', $record->unique_letters->toArray());
        $this->assertContains('e', $record->unique_letters->toArray());
        $this->assertContains('s', $record->unique_letters->toArray());
        $this->assertContains('1', $record->unique_letters->toArray());
        $this->assertContains('2', $record->unique_letters->toArray());
        $this->assertContains('3', $record->unique_letters->toArray());
    }

    public function test_unparse_single_record(): void
    {
        $words = ['test'];
        $collection = $this->service->parse($words);
        $result = $this->service->unparse($collection);

        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertEquals(1, $result->count());

        $uri = $result->first();
        $this->assertStringContainsString('test?', $uri);
        $this->assertStringContainsString('metaphone=', $uri);
        $this->assertStringContainsString('unique_letters', $uri);
        $this->assertStringContainsString('bigrams', $uri);
        $this->assertStringContainsString('metaphone_bigrams', $uri);
    }

    public function test_unparse_multiple_records(): void
    {
        $words = ['test', 'hello'];
        $collection = $this->service->parse($words);
        $result = $this->service->unparse($collection);

        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertEquals(2, $result->count());

        $uris = $result->toArray();
        $this->assertStringContainsString('test?', $uris[0]);
        $this->assertStringContainsString('hello?', $uris[1]);
    }

    public function test_unparse_empty_collection(): void
    {
        $collection = new WordVectorCollection;
        $result = $this->service->unparse($collection);

        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_parse_unparse_roundtrip(): void
    {
        $originalWords = ['test', 'hello', 'world'];
        $collection = $this->service->parse($originalWords);
        $uris = $this->service->unparse($collection);
        $newCollection = $this->service->parse($uris->toArray());

        $this->assertEquals($collection->count(), $newCollection->count());

        $originalRecords = $collection->toArray();
        $newRecords = $newCollection->toArray();

        for ($i = 0; $i < count($originalRecords); $i++) {
            $this->assertEquals($originalRecords[$i]->word, $newRecords[$i]->word);
            $this->assertEquals($originalRecords[$i]->metaphone, $newRecords[$i]->metaphone);
            $this->assertEquals(
                $originalRecords[$i]->unique_letters->toArray(),
                $newRecords[$i]->unique_letters->toArray()
            );
            $this->assertEquals(
                $originalRecords[$i]->bigrams->toArray(),
                $newRecords[$i]->bigrams->toArray()
            );
            $this->assertEquals(
                $originalRecords[$i]->metaphone_bigrams->toArray(),
                $newRecords[$i]->metaphone_bigrams->toArray()
            );
        }
    }

    public function test_parse_with_word_containing_uri(): void
    {
        $words = ['test?metaphone=TST'];
        $result = $this->service->parse($words);

        $record = $result->first();
        $this->assertEquals('test', $record->word);
        $this->assertNotEmpty($record->metaphone);
    }

    public function test_parse_with_realistic_data(): void
    {
        $words = ['ordinateur', 'portable', 'gaming'];
        $result = $this->service->parse($words);

        $this->assertEquals(3, $result->count());

        foreach ($result as $record) {
            $this->assertNotEmpty($record->word);
            $this->assertNotEmpty($record->metaphone);
            $this->assertNotEmpty($record->unique_letters->toArray());
            $this->assertNotEmpty($record->bigrams->toArray());
            $this->assertNotEmpty($record->metaphone_bigrams->toArray());
        }
    }

    public function test_parse_with_french_words(): void
    {
        $words = ['éléphant', 'café', 'thé'];
        $result = $this->service->parse($words);

        $this->assertEquals(3, $result->count());

        $records = $result->toArray();
        $this->assertEquals('éléphant', $records[0]->word);
        $this->assertEquals('café', $records[1]->word);
        $this->assertEquals('thé', $records[2]->word);

        foreach ($records as $record) {
            $this->assertNotEmpty($record->unique_letters->toArray());
            $this->assertNotEmpty($record->bigrams->toArray());
        }
    }

    public function test_parse_with_stopwords(): void
    {
        $words = ['le', 'la', 'les'];
        $result = $this->service->parse($words);

        $this->assertEquals(3, $result->count());

        foreach ($result as $record) {
            $this->assertNotEmpty($record->metaphone);
            $this->assertNotEmpty($record->bigrams->toArray());
        }
    }

    public function test_parse_uris_to_collection(): void
    {
        $uris = [
            'john?metaphone=JN&unique_letters%5B0%5D=j&unique_letters%5B1%5D=o&unique_letters%5B2%5D=h&unique_letters%5B3%5D=n&bigrams%5B0%5D=jo&bigrams%5B1%5D=oh&bigrams%5B2%5D=hn&metaphone_bigrams%5B0%5D=JN',
            'doe?metaphone=T&unique_letters%5B0%5D=d&unique_letters%5B1%5D=o&unique_letters%5B2%5D=e&bigrams%5B0%5D=do&bigrams%5B1%5D=oe',
        ];

        $result = $this->service->parseUrisToCollection($uris);

        $this->assertInstanceOf(WordVectorCollection::class, $result);
        $this->assertEquals(2, $result->count());

        $records = $result->toArray();

        $this->assertEquals('john', $records[0]->word);
        $this->assertEquals('JN', $records[0]->metaphone);
        $this->assertEquals(['j', 'o', 'h', 'n'], $records[0]->unique_letters->toArray());
        $this->assertEquals(['jo', 'oh', 'hn'], $records[0]->bigrams->toArray());
        $this->assertEquals(['JN'], $records[0]->metaphone_bigrams->toArray());

        $this->assertEquals('doe', $records[1]->word);
        $this->assertEquals('T', $records[1]->metaphone);
        $this->assertEquals(['d', 'o', 'e'], $records[1]->unique_letters->toArray());
        $this->assertEquals(['do', 'oe'], $records[1]->bigrams->toArray());
        $this->assertEquals([], $records[1]->metaphone_bigrams->toArray());
    }

    public function test_parse_uris_to_collection_empty(): void
    {
        $result = $this->service->parseUrisToCollection([]);

        $this->assertInstanceOf(WordVectorCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_unparse_collection_to_uris(): void
    {
        $uris = [
            'john?metaphone=JN&unique_letters%5B0%5D=j&unique_letters%5B1%5D=o&unique_letters%5B2%5D=h&unique_letters%5B3%5D=n&bigrams%5B0%5D=jo&bigrams%5B1%5D=oh&bigrams%5B2%5D=hn&metaphone_bigrams%5B0%5D=JN',
            'doe?metaphone=T&unique_letters%5B0%5D=d&unique_letters%5B1%5D=o&unique_letters%5B2%5D=e&bigrams%5B0%5D=do&bigrams%5B1%5D=oe',
        ];

        $collection = $this->service->parseUrisToCollection($uris);
        $result = $this->service->unparseCollectionToUris($collection);

        $this->assertInstanceOf(StringTypedCollection::class, $result);
        $this->assertEquals($uris, $result->toArray());
    }

    public function test_parse_and_unparse_roundtrip_with_collection(): void
    {
        $originalUris = [
            'john?metaphone=JN&unique_letters%5B0%5D=j&unique_letters%5B1%5D=o&unique_letters%5B2%5D=h&unique_letters%5B3%5D=n&bigrams%5B0%5D=jo&bigrams%5B1%5D=oh&bigrams%5B2%5D=hn&metaphone_bigrams%5B0%5D=JN',
            'doe?metaphone=T&unique_letters%5B0%5D=d&unique_letters%5B1%5D=o&unique_letters%5B2%5D=e&bigrams%5B0%5D=do&bigrams%5B1%5D=oe',
        ];

        $collection = $this->service->parseUrisToCollection($originalUris);
        $unparsed = $this->service->unparseCollectionToUris($collection);

        $this->assertEquals($originalUris, $unparsed->toArray());
    }
}
