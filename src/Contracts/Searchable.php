<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;

interface Searchable
{
    /**
     * Retourne les champs à indexer.
     */
    public function getSearchableFields(): SearchableFieldCollection;

    /**
     * Détermine si l'enregistrement doit être indexé.
     */
    public function shouldBeIndexed(): bool;

    /**
     * Retourne le Record de formatage personnalisé.
     */
    public function getFuzzyFormat(): ?AbstractRecord;

    /**
     * Retourne les champs protégés où les stop words sont préservés.
     */
    public function getProtectedFields(): StringTypedCollection;

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass();

    /**
     * Get the value of the model's primary key.
     *
     * @return int|string
     */
    public function getKey();
}
