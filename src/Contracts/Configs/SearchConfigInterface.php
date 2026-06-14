<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Configs;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Records\CacheRecord;
use AndyDefer\LaravelSearch\Records\EngineRecord;

interface SearchConfigInterface
{
    public function getEngine(): EngineRecord;

    public function getCache(): CacheRecord;

    public function getTableName(): string;

    public function getBatchSize(): int;

    public function isAutoIndexEnabled(): bool;

    public function getModels(): StringTypedCollection;

    // Nouvelles méthodes pour SearchEngineService
    public function getCacheTtl(): int;

    public function getCachePrefix(): string;

    public function getRelevanceThreshold(): float;

    public function getMinQueryLength(): int;

    public function getMaxWordLengthForHash(): int;
}
