<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts;

use AndyDefer\DomainStructures\Utils\StrictAssociative;

interface Indexable
{
    /**
     * Détermine si l'entité doit être indexée
     */
    public function shouldBeIndexed(): bool;

    /**
     * Retourne les données à indexer sous forme de StrictAssociative
     * Clé = nom de la colonne (source_column), Valeur = contenu à indexer
     *
     * Exemple:
     * return StrictAssociative::from([
     *     'name' => $this->user->name,
     *     'email' => $this->user->email,
     *     'description' => $this->description,
     * ]);
     */
    public function getIndexableData(): StrictAssociative;

    /**
     * Retourne le type de l'entité (morph class)
     */
    public function getMorphClass();

    /**
     * Retourne l'ID de l'entité
     */
    public function getKey();
}
