<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Unit\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contexts\NormalizerContext;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;
use AndyDefer\LaravelSearch\Services\NormalizerService;
use AndyDefer\LaravelSearch\Tests\UnitTestCase;
use AndyDefer\PhpServices\Enums\PrimitiveType;

final class NormalizerServiceTest extends UnitTestCase
{
    private NormalizerContext $context;

    private NormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new NormalizerContext;
        $this->normalizer = new NormalizerService($this->context);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==================== Tests: normalizeString ====================

    public function test_normalize_string_removes_accents(): void
    {
        $result = $this->normalizer->normalizeString('Éléphant');
        $this->assertSame('elephant', $result);
    }

    public function test_normalize_string_converts_to_lowercase(): void
    {
        $result = $this->normalizer->normalizeString('HELLO World');
        $this->assertSame('hello world', $result);
    }

    public function test_normalize_string_removes_special_characters(): void
    {
        $result = $this->normalizer->normalizeString('Hello@World!');
        $this->assertSame('hello world', $result);
    }

    public function test_normalize_string_preserves_hyphens_and_apostrophes(): void
    {
        $result = $this->normalizer->normalizeString("Jean-Pierre d'Arc");
        $this->assertSame("jean-pierre d'arc", $result);
    }

    public function test_normalize_string_trims_extra_spaces(): void
    {
        $result = $this->normalizer->normalizeString('  Hello   World  ');
        $this->assertSame('hello world', $result);
    }

    public function test_normalize_string_caches_result(): void
    {
        $result1 = $this->normalizer->normalizeString('Test String');
        $result2 = $this->normalizer->normalizeString('Test String');

        $this->assertSame($result1, $result2);
        $this->assertTrue($this->context->has('Test String'));
        $this->assertSame('test string', $this->context->getNormalized('Test String'));
    }

    public function test_normalize_string_handles_accented_characters(): void
    {
        $result = $this->normalizer->normalizeString('Café Crème Brûlée');
        $this->assertSame('cafe creme brulee', $result);
    }

    public function test_normalize_string_handles_german_umlauts(): void
    {
        $result = $this->normalizer->normalizeString('München Straße');
        $this->assertSame('munchen strasse', $result);
    }

    // ==================== Tests: normalizeField ====================

    public function test_normalize_field_normalizes_unprotected_field(): void
    {
        $field = new SearchableFieldRecord(
            name: 'description',
            value: 'Hello World!',
            primitive_type: PrimitiveType::STRING,
        );

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeField($field, $protectedFields);

        $this->assertSame('description', $result->name);
        $this->assertSame('hello world', $result->value);
        $this->assertSame(PrimitiveType::STRING, $result->primitive_type);
    }

    public function test_normalize_field_does_not_normalize_protected_field(): void
    {
        $field = new SearchableFieldRecord(
            name: 'email',
            value: 'John.Doe@Example.com',
            primitive_type: PrimitiveType::STRING,
        );

        $protectedFields = new StringTypedCollection;
        $protectedFields->add('email');

        $result = $this->normalizer->normalizeField($field, $protectedFields);

        $this->assertSame('email', $result->name);
        $this->assertSame('John.Doe@Example.com', $result->value);
    }

    public function test_normalize_field_preserves_original_type(): void
    {
        $field = new SearchableFieldRecord(
            name: 'age',
            value: '25',
            primitive_type: PrimitiveType::INT,
        );

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeField($field, $protectedFields);

        $this->assertSame(PrimitiveType::INT, $result->primitive_type);
    }

    // ==================== Tests: normalizeCollection ====================

    public function test_normalize_collection_normalizes_all_fields(): void
    {
        $collection = new SearchableFieldCollection;
        $collection->add(new SearchableFieldRecord(
            name: 'title',
            value: 'Hello World!',
            primitive_type: PrimitiveType::STRING,
        ));
        $collection->add(new SearchableFieldRecord(
            name: 'body',
            value: 'This is a test.',
            primitive_type: PrimitiveType::STRING,
        ));

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeCollection($collection, $protectedFields);

        $this->assertCount(2, $result);
        $this->assertSame('hello world', $result->first()->value);
        $this->assertSame('this is a test', $result->last()->value);
    }

    public function test_normalize_collection_respects_protected_fields(): void
    {
        $collection = new SearchableFieldCollection;
        $collection->add(new SearchableFieldRecord(
            name: 'email',
            value: 'John.Doe@Example.com',
            primitive_type: PrimitiveType::STRING,
        ));
        $collection->add(new SearchableFieldRecord(
            name: 'name',
            value: 'John Doe',
            primitive_type: PrimitiveType::STRING,
        ));

        $protectedFields = new StringTypedCollection;
        $protectedFields->add('email');

        $result = $this->normalizer->normalizeCollection($collection, $protectedFields);

        $this->assertSame('John.Doe@Example.com', $result->first()->value);
        $this->assertSame('john doe', $result->last()->value);
    }

    public function test_normalize_collection_returns_new_collection(): void
    {
        $collection = new SearchableFieldCollection;
        $collection->add(new SearchableFieldRecord(
            name: 'test',
            value: 'Value',
            primitive_type: PrimitiveType::STRING,
        ));

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeCollection($collection, $protectedFields);

        $this->assertNotSame($collection, $result);
        $this->assertCount(1, $result);
    }

    public function test_normalize_collection_handles_empty_collection(): void
    {
        $collection = new SearchableFieldCollection;
        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeCollection($collection, $protectedFields);

        $this->assertCount(0, $result);
    }

    // ==================== Tests: buildContentString ====================

    public function test_build_content_string_concatenates_field_values(): void
    {
        $collection = new SearchableFieldCollection;
        $collection->add(new SearchableFieldRecord(
            name: 'title',
            value: 'Hello',
            primitive_type: PrimitiveType::STRING,
        ));
        $collection->add(new SearchableFieldRecord(
            name: 'body',
            value: 'World',
            primitive_type: PrimitiveType::STRING,
        ));

        $result = $this->normalizer->buildContentString($collection);

        $this->assertSame('Hello World', $result);
    }

    public function test_build_content_string_handles_single_field(): void
    {
        $collection = new SearchableFieldCollection;
        $collection->add(new SearchableFieldRecord(
            name: 'title',
            value: 'Hello',
            primitive_type: PrimitiveType::STRING,
        ));

        $result = $this->normalizer->buildContentString($collection);

        $this->assertSame('Hello', $result);
    }

    public function test_build_content_string_handles_empty_collection(): void
    {
        $collection = new SearchableFieldCollection;

        $result = $this->normalizer->buildContentString($collection);

        $this->assertSame('', $result);
    }

    // ==================== Tests: getContext ====================

    public function test_get_context_returns_same_context(): void
    {
        $context = $this->normalizer->getContext();

        $this->assertSame($this->context, $context);
    }

    // ==================== Tests: Cache behavior ====================

    public function test_normalize_string_uses_cache_on_second_call(): void
    {
        // Première normalisation - remplit le cache
        $this->normalizer->normalizeString('Unique String');

        // Vérifier que le cache contient la valeur
        $this->assertTrue($this->context->has('Unique String'));

        // Nettoyer le contexte pour simuler un nouveau contexte
        $this->context->clearCache();
        $this->assertFalse($this->context->has('Unique String'));

        // Deuxième normalisation - doit recalculer
        $result = $this->normalizer->normalizeString('Unique String');
        $this->assertTrue($this->context->has('Unique String'));
    }

    // ==================== Tests: Edge cases ====================

    public function test_normalize_string_handles_empty_string(): void
    {
        $result = $this->normalizer->normalizeString('');

        $this->assertSame('', $result);
    }

    public function test_normalize_string_handles_only_special_characters(): void
    {
        $result = $this->normalizer->normalizeString('!!!@@@###');

        $this->assertSame('', $result);
    }

    public function test_normalize_string_handles_numbers(): void
    {
        $result = $this->normalizer->normalizeString('123456');

        $this->assertSame('123456', $result);
    }

    public function test_normalize_string_handles_mixed_alphanumeric(): void
    {
        $result = $this->normalizer->normalizeString('Product ABC123');

        $this->assertSame('product abc123', $result);
    }

    public function test_normalize_field_with_integer_value(): void
    {
        $field = new SearchableFieldRecord(
            name: 'count',
            value: '100',
            primitive_type: PrimitiveType::INT,
        );

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeField($field, $protectedFields);

        $this->assertSame('100', $result->value);
    }

    public function test_normalize_field_with_boolean_value(): void
    {
        $field = new SearchableFieldRecord(
            name: 'is_active',
            value: 'true',
            primitive_type: PrimitiveType::BOOL,
        );

        $protectedFields = new StringTypedCollection;

        $result = $this->normalizer->normalizeField($field, $protectedFields);

        $this->assertSame('true', $result->value);
    }
}
