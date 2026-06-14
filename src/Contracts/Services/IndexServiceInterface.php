<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\ValueObjects\IndexStatsVO;

interface IndexServiceInterface
{
    /**
     * Indexe un modèle unique.
     *
     * @param Searchable $model Le modèle à indexer
     * @param bool $force Si true, réindexe même si déjà existant
     * @return bool True si l'indexation a été effectuée, false si déjà indexé (sans force)
     */
    public function index(Searchable $model, bool $force = false): bool;

    /**
     * Met à jour l'index pour un modèle (force la réindexation).
     *
     * @param Searchable $model Le modèle à mettre à jour
     */
    public function updateIndex(Searchable $model): void;

    /**
     * Supprime l'index d'un modèle.
     *
     * @param Searchable $model Le modèle à supprimer de l'index
     */
    public function deleteIndex(Searchable $model): void;

    /**
     * Indexe une collection de modèles.
     *
     * @param StringTypedCollection $modelClasses Liste des classes de modèles à indexer
     * @param bool $force Si true, réindexe même si déjà existant
     * @param callable|null $callback Callback optionnel pour le progrès
     * @return IndexStatsVO Statistiques d'indexation
     * @throws \InvalidArgumentException Si une classe de modèle n'existe pas ou n'implémente pas Searchable
     */
    public function indexAll(StringTypedCollection $modelClasses, bool $force = false, ?callable $callback = null): IndexStatsVO;

    /**
     * Vide complètement l'index.
     */
    public function clearIndex(): void;
}
