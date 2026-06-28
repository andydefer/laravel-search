<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Collections\SearchIndexCollection;
use AndyDefer\LaravelSearch\Contracts\Indexable;
use AndyDefer\LaravelSearch\Records\SyncResultRecord;

interface SearchIndexInterface
{
    /**
     * Indexe une entité (un index par colonne)
     *
     * @param  Indexable  $entity  L'entité à indexer
     * @return SearchIndexCollection Collection des indexes créés
     */
    public function index(Indexable $entity): SearchIndexCollection;

    /**
     * Indexe toutes les entités d'un type
     */
    public function indexAll(string $morphClass, int $batchSize = 100): int;

    /**
     * Indexe toutes les entités avec callback
     */
    public function indexAllWithCallback(string $morphClass, callable $callback, int $batchSize = 100): int;

    /**
     * Réindexe une entité
     */
    public function reindex(Indexable $entity): SearchIndexCollection;

    /**
     * Réindexe toutes les entités d'un type
     */
    public function reindexAll(string $morphClass, int $batchSize = 100): int;

    /**
     * Réindexe toutes les entités avec callback
     */
    public function reindexAllWithCallback(string $morphClass, callable $callback, int $batchSize = 100): int;

    /**
     * Supprime les indexes d'une entité
     */
    public function delete(Indexable $entity): bool;

    /**
     * Supprime tous les indexes d'un type
     */
    public function deleteAll(string $morphClass): int;

    /**
     * Supprime tous les indexes avec callback
     */
    public function deleteAllWithCallback(string $morphClass, callable $callback): int;

    /**
     * Supprime un index par son ID
     */
    public function deleteById(string $id): bool;

    /**
     * Supprime les indexes d'une entité par son ID
     */
    public function deleteByEntityId(string $morphClass, string $entityId): bool;

    /**
     * Récupère le nombre d'indexes pour un type
     */
    public function getIndexedCount(string $morphClass): int;

    /**
     * Récupère le nombre d'entités non indexées
     */
    public function getNotIndexedCount(string $morphClass): int;

    /**
     * Synchronise les indexes avec les entités
     */
    public function sync(string $morphClass, int $batchSize = 100): SyncResultRecord;
}
