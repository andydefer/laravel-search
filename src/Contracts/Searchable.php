<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts;

use AndyDefer\DomainStructures\Utils\StrictAssociative;

interface Searchable extends Indexable
{
    /**
     * Définit le format de sortie du modèle pour les résultats de recherche
     *
     * Exemple:
     * return StrictAssociative::from([
     *     'id' => $this->id,
     *     'name' => $this->name,
     *     'email' => $this->email,
     *     'created_at' => $this->created_at->format('Y-m-d'),
     * ]);
     */
    public function getSearchResultFormat(): StrictAssociative;
}
