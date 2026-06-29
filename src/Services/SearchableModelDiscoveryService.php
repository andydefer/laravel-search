<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelSearch\Collections\SearchableModelCollection;
use AndyDefer\LaravelSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Contracts\Services\SearchableModelDiscoveryInterface;
use AndyDefer\LaravelSearch\Records\SearchableModelRecord;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class SearchableModelDiscoveryService implements SearchableModelDiscoveryInterface
{
    public function __construct(
        private readonly FileSystemInterface $files,
        private readonly SearchConfigInterface $config,
    ) {}

    public function discover(): MapCollection
    {
        $models = [];
        $paths = $this->config->getSearchablePaths();

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            $found = $this->scanDirectory($path);

            foreach ($found as $key => $value) {
                $models[$key] = $value;
            }
        }

        return MapCollection::from($models);
    }

    private function scanDirectory(string $directory): array
    {
        $models = [];

        $files = $this->files->glob($directory.'/*.php');

        foreach ($files as $file) {
            $className = $this->extractClassNameFromFile($file);

            if ($className === null) {
                continue;
            }

            if ($this->isSearchableClass($className)) {
                $models[$className] = $file;
            }
        }

        $subDirectories = $this->files->glob($directory.'/*', GLOB_ONLYDIR);

        foreach ($subDirectories as $subDirectory) {
            $subModels = $this->scanDirectory($subDirectory);

            foreach ($subModels as $key => $value) {
                $models[$key] = $value;
            }
        }

        return $models;
    }

    private function extractClassNameFromFile(string $filePath): ?string
    {
        $content = $this->files->get($filePath);

        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        preg_match('/class\s+([a-zA-Z0-9_]+)\s*/', $content, $classMatches);
        $className = $classMatches[1] ?? '';

        if (empty($namespace) || empty($className)) {
            return null;
        }

        $fullClassName = $namespace.'\\'.$className;

        if (! class_exists($fullClassName)) {
            return null;
        }

        return $fullClassName;
    }

    private function isSearchableClass(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                return false;
            }

            return $reflection->implementsInterface(Searchable::class);
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    public function discoverWithMetadata(): SearchableModelCollection
    {
        $collection = new SearchableModelCollection;

        foreach ($this->discover() as $className => $path) {
            $reflection = new \ReflectionClass($className);
            $table = null;

            if ($reflection->hasMethod('getTable')) {
                try {
                    $instance = $reflection->newInstance();
                    if (method_exists($instance, 'getTable')) {
                        $table = $instance->getTable();
                    }
                } catch (\Exception $e) {
                    // Impossible d'instancier la classe
                }
            }

            $collection->add(SearchableModelRecord::from([
                'class' => StringVO::from($className),
                'path' => StringVO::from($path),
                'morph_class' => StringVO::from($className),
                'table' => $table ? StringVO::from($table) : null,
            ]));
        }

        return $collection;
    }

    public function count(): int
    {
        return $this->discover()->count();
    }

    public function isSearchable(string $className): bool
    {
        return $this->discover()->hasKey($className);
    }

    public function findByMorphClass(string $morphClass): ?string
    {
        foreach ($this->discover() as $className => $path) {
            if ($className === $morphClass) {
                return $path;
            }
        }

        return null;
    }
}
