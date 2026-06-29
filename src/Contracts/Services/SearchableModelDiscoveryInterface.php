<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelSearch\Collections\SearchableModelCollection;

interface SearchableModelDiscoveryInterface
{
    /**
     * Découvre tous les modèles Searchable dans les dossiers configurés
     *
     * @return MapCollection<string, string> Collection [nom_classe => chemin_fichier]
     */
    public function discover(): MapCollection;

    /**
     * Récupère toutes les classes Searchable avec leurs métadonnées sous forme de collection typée
     */
    public function discoverWithMetadata(): SearchableModelCollection;

    /**
     * Compte le nombre de modèles Searchable trouvés
     */
    public function count(): int;

    /**
     * Vérifie si un modèle est Searchable
     */
    public function isSearchable(string $className): bool;

    /**
     * Trouve une classe par son morph class
     */
    public function findByMorphClass(string $morphClass): ?string;
}
