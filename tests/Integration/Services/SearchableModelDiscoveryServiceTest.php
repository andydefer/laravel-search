<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Unit\Services;

use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelSearch\Collections\SearchableModelCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Records\SearchableModelRecord;
use AndyDefer\LaravelSearch\Services\SearchableModelDiscoveryService;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestAddress;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestNonSearchableModel;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelSearch\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelSearch\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SearchableModelDiscoveryServiceTest extends IntegrationTestCase
{
    private SearchableModelDiscoveryService $service;

    private SearchConfig $config;

    private FileSystemInterface $files;

    protected function setUp(): void
    {
        parent::setUp();

        $configRepository = app(ConfigRepository::class);

        // Configurer les chemins pour les tests
        $configRepository->set('search.searchable_paths', [
            __DIR__.'/../../Fixtures/Models',
        ]);

        $this->config = new SearchConfig($configRepository);
        $this->files = app(FileSystemInterface::class);

        $this->service = new SearchableModelDiscoveryService(
            $this->files,
            $this->config,
        );
    }

    public function test_discover_returns_map_collection(): void
    {
        $result = $this->service->discover();

        $this->assertInstanceOf(MapCollection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        // Vérifier que les modèles fixtures sont trouvés
        $classes = $result->keys()->toArray();
        $this->assertContains(TestUser::class, $classes);
        $this->assertContains(TestAddress::class, $classes);
        $this->assertContains(TestProduct::class, $classes);
    }

    public function test_discover_returns_class_as_key_and_path_as_value(): void
    {
        $result = $this->service->discover();

        foreach ($result as $className => $path) {
            $this->assertIsString($className);
            $this->assertIsString($path);
            $this->assertTrue($this->files->exists($path));
            $this->assertTrue(class_exists($className));
        }
    }

    public function test_discover_with_metadata_returns_searchable_model_collection(): void
    {
        $result = $this->service->discoverWithMetadata();

        $this->assertInstanceOf(SearchableModelCollection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $record) {
            $this->assertInstanceOf(SearchableModelRecord::class, $record);
            $this->assertInstanceOf(StringVO::class, $record->class);
            $this->assertInstanceOf(StringVO::class, $record->path);
            $this->assertInstanceOf(StringVO::class, $record->morph_class);

            $this->assertTrue($this->files->exists($record->path->getValue()));
            $this->assertTrue(class_exists($record->class->getValue()));
        }
    }

    public function test_discover_with_metadata_contains_fixtures(): void
    {
        $result = $this->service->discoverWithMetadata();

        $classes = $result->getClassNames();

        $this->assertContains(TestUser::class, $classes);
        $this->assertContains(TestAddress::class, $classes);
        $this->assertContains(TestProduct::class, $classes);
    }

    public function test_discover_ignores_non_searchable_models(): void
    {
        $result = $this->service->discover();

        $classes = $result->keys()->toArray();

        // TestNonSearchableModel n'implémente pas Searchable
        $this->assertNotContains(TestNonSearchableModel::class, $classes);
    }

    public function test_find_by_class(): void
    {
        $result = $this->service->discoverWithMetadata();

        $record = $result->findByClass(TestUser::class);

        $this->assertNotNull($record);
        $this->assertEquals(TestUser::class, $record->class->getValue());
        $this->assertEquals(TestUser::class, $record->morph_class->getValue());
    }

    public function test_find_by_morph_class(): void
    {
        $result = $this->service->discoverWithMetadata();

        $record = $result->findByMorphClass(TestUser::class);

        $this->assertNotNull($record);
        $this->assertEquals(TestUser::class, $record->class->getValue());
    }

    public function test_find_by_class_not_found(): void
    {
        $result = $this->service->discoverWithMetadata();

        $record = $result->findByClass('NonExistentClass');

        $this->assertNull($record);
    }

    public function test_count(): void
    {
        $count = $this->service->count();

        $this->assertGreaterThan(0, $count);

        // Vérifier que le compte correspond au nombre de modèles Searchable
        $discovered = $this->service->discover();
        $this->assertEquals($discovered->count(), $count);
    }

    public function test_is_searchable(): void
    {
        $this->assertTrue($this->service->isSearchable(TestUser::class));
        $this->assertTrue($this->service->isSearchable(TestAddress::class));
        $this->assertTrue($this->service->isSearchable(TestProduct::class));
        $this->assertFalse($this->service->isSearchable(TestNonSearchableModel::class));
        $this->assertFalse($this->service->isSearchable('NonExistentClass'));
    }

    public function test_find_by_morph_class_returns_null_when_not_found(): void
    {
        $result = $this->service->findByMorphClass('NonExistentClass');

        $this->assertNull($result);
    }

    public function test_searchable_model_record_to_array(): void
    {
        $record = SearchableModelRecord::from([
            'class' => StringVO::from(TestUser::class),
            'path' => StringVO::from('/path/to/User.php'),
            'morph_class' => StringVO::from(TestUser::class),
            'table' => StringVO::from('users'),
        ]);

        $array = $record->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('class', $array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('morph_class', $array);
        $this->assertArrayHasKey('table', $array);
        $this->assertEquals(TestUser::class, $array['class']);
        $this->assertEquals('/path/to/User.php', $array['path']);
        $this->assertEquals(TestUser::class, $array['morph_class']);
        $this->assertEquals('users', $array['table']);
    }

    public function test_searchable_model_record_with_null_table(): void
    {
        $record = SearchableModelRecord::from([
            'class' => StringVO::from(TestUser::class),
            'path' => StringVO::from('/path/to/User.php'),
            'morph_class' => StringVO::from(TestUser::class),
            'table' => null,
        ]);

        $array = $record->toArray();

        $this->assertArrayHasKey('table', $array);
        $this->assertNull($array['table']);
    }

    public function test_searchable_model_collection_methods(): void
    {
        $collection = $this->service->discoverWithMetadata();

        $classNames = $collection->getClassNames();
        $paths = $collection->getPaths();
        $morphClasses = $collection->getMorphClasses();
        $tables = $collection->getTables();

        $this->assertIsArray($classNames);
        $this->assertIsArray($paths);
        $this->assertIsArray($morphClasses);
        $this->assertIsArray($tables);
        $this->assertCount($collection->count(), $classNames);
        $this->assertCount($collection->count(), $paths);
        $this->assertCount($collection->count(), $morphClasses);
        $this->assertCount($collection->count(), $tables);
    }

    public function test_discover_with_custom_paths(): void
    {
        $configRepository = app(ConfigRepository::class);
        $configRepository->set('search.searchable_paths', [
            __DIR__.'/../../Fixtures/Models',
        ]);

        $config = new SearchConfig($configRepository);
        $service = new SearchableModelDiscoveryService(
            $this->files,
            $config,
        );

        $result = $service->discover();

        $this->assertGreaterThan(0, $result->count());
        $this->assertTrue($result->hasKey(TestUser::class));
        $this->assertTrue($result->hasKey(TestAddress::class));
        $this->assertTrue($result->hasKey(TestProduct::class));
    }

    public function test_discover_with_empty_paths_returns_empty(): void
    {
        $configRepository = app(ConfigRepository::class);
        $configRepository->set('search.searchable_paths', []);

        $config = new SearchConfig($configRepository);
        $service = new SearchableModelDiscoveryService(
            $this->files,
            $config,
        );

        $result = $service->discover();

        $this->assertEquals(0, $result->count());
    }
}
