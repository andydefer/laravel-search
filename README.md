# Laravel Fuzzy Search

**Un moteur de recherche floue pour Laravel basé sur les n-grammes, avec stockage JSONL et cache PSR-16.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Concepts fondamentaux](#concepts-fondamentaux)
5. [Préparation des modèles](#préparation-des-modèles)
6. [Indexation des données](#indexation-des-données)
7. [Recherche](#recherche)
8. [Cache](#cache)
9. [Commandes CLI](#commandes-cli)
10. [Architecture technique](#architecture-technique)
11. [Tests](#tests)
12. [Licence](#licence)

---

## Introduction

### Le problème

Les recherches SQL standard (`LIKE '%term%'`) sont :
- Lentes sur de grands volumes
- Insensibles à la casse uniquement avec des collations spécifiques
- Inefficaces pour les fautes de frappe
- Non pertinentes (pas de scoring)

### La solution : Laravel Fuzzy Search

**Laravel Fuzzy Search** est un moteur de recherche floue qui utilise les **n-grammes** pour trouver des correspondances partielles, même avec des fautes de frappe.

| Problème | Solution Laravel Fuzzy Search |
|----------|-------------------------------|
| Recherche `LIKE` lente | Indexation pré-calculée |
| Fautes de frappe non gérées | Match par n-grammes (bigrammes, trigrammes) |
| Pas de pertinence | Scoring avec pourcentage de match |
| Pas de cache natif | Cache PSR-16 avec JSONL |
| Configuration complexe | Zéro configuration, prêt à l'emploi |

---

## Installation

```bash
composer require andydefer/laravel-fuzzy-search
```

### Publication des fichiers (Laravel)

```bash
# Publier la configuration
php artisan vendor:publish --tag=fuzzy-search-config

# Publier les migrations
php artisan vendor:publish --tag=fuzzy-search-migrations
```

### Installation via directive CLI

```bash
./vendor/bin/directive fuzzy-search-install
```

Cette commande publie automatiquement la configuration, les migrations et les exécute.

### Migration

```bash
php artisan migrate
```

---

## Configuration

```php
// config/fuzzy-search.php

return [
    // Modèles à indexer automatiquement
    'models' => [
        App\Models\User::class,
        App\Models\Product::class,
    ],

    // Nom de la table d'index
    'table_name' => env('FUZZY_SEARCH_TABLE_NAME', 'search_index'),

    // Taille des lots pour l'indexation
    'batch_size' => env('FUZZY_SEARCH_BATCH_SIZE', 100),

    // Configuration du moteur de recherche
    'engine' => [
        'min_gram_length' => env('FUZZY_SEARCH_MIN_GRAM_LENGTH', 2),      // Bigrammes
        'max_gram_length' => env('FUZZY_SEARCH_MAX_GRAM_LENGTH', 4),      // Jusqu'à 4-grammes
        'min_letters_match_percentage' => env('FUZZY_SEARCH_MIN_LETTERS_MATCH_PERCENTAGE', 30),
        'min_length_ratio' => env('FUZZY_SEARCH_MIN_LENGTH_RATIO', 0.5),
        'max_candidates_per_word' => env('FUZZY_SEARCH_MAX_CANDIDATES_PER_WORD', 5),
        'early_stop_threshold' => env('FUZZY_SEARCH_EARLY_STOP_THRESHOLD', 0.95),
    ],

    // Configuration du cache
    'cache' => [
        'enabled' => env('FUZZY_SEARCH_CACHE_ENABLED', true),
        'ttl' => env('FUZZY_SEARCH_CACHE_TTL', 3600),    // 1 heure
        'prefix' => env('FUZZY_SEARCH_CACHE_PREFIX', 'fuzzy_search_'),
    ],

    // Seuil de pertinence minimum (en pourcentage)
    'relevance_threshold' => env('FUZZY_SEARCH_RELEVANCE_THRESHOLD', 10.0),

    // Longueur minimale de la requête
    'min_query_length' => env('FUZZY_SEARCH_MIN_QUERY_LENGTH', 1),

    // Longueur maximale pour le hash des clés de cache
    'max_word_length_for_hash' => env('FUZZY_SEARCH_MAX_WORD_LENGTH_FOR_HASH', 64),
];
```

### Variables d'environnement

```env
FUZZY_SEARCH_TABLE_NAME=search_index
FUZZY_SEARCH_BATCH_SIZE=100
FUZZY_SEARCH_MIN_GRAM_LENGTH=2
FUZZY_SEARCH_MAX_GRAM_LENGTH=4
FUZZY_SEARCH_MIN_LETTERS_MATCH_PERCENTAGE=30
FUZZY_SEARCH_MIN_LENGTH_RATIO=0.5
FUZZY_SEARCH_MAX_CANDIDATES_PER_WORD=5
FUZZY_SEARCH_EARLY_STOP_THRESHOLD=0.95
FUZZY_SEARCH_CACHE_ENABLED=true
FUZZY_SEARCH_CACHE_TTL=3600
FUZZY_SEARCH_CACHE_PREFIX=fuzzy_search_
FUZZY_SEARCH_RELEVANCE_THRESHOLD=10.0
FUZZY_SEARCH_MIN_QUERY_LENGTH=1
FUZZY_SEARCH_MAX_WORD_LENGTH_FOR_HASH=64
```

---

## Concepts fondamentaux

### Comment fonctionne la recherche floue ?

1. **Normalisation** : Suppression des accents, mise en minuscules, nettoyage
2. **Découpage en mots** : Séparation par espaces
3. **Génération de n-grammes** : Création de sous-chaînes de longueur 2-4
4. **Match** : Comparaison des n-grammes entre requête et index
5. **Scoring** : Calcul d'un pourcentage de pertinence

### Exemple de n-grammes

Pour le mot **"bonjour"** :
- Bigrammes (2) : bo, on, nj, jo, ou, ur
- Trigrammes (3) : bon, onj, njo, jou, our
- 4-grammes (4) : bonj, onjo, njou, jour

### Structure de la table d'index

```sql
search_index (
    id,
    searchable_type,      -- Type du modèle (polymorphique)
    searchable_id,        -- ID du modèle
    content,              -- Contenu original
    normalized_content,   -- Contenu normalisé
    fields,               -- Champs indexés (JSON)
    created_at,
    updated_at
)
```

---

## Préparation des modèles

### Implémenter l'interface `Searchable`

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use Illuminate\Database\Eloquent\Model;

final class User extends Model implements Searchable
{
    /**
     * Retourne les champs à indexer.
     */
    public function getSearchableFields(): SearchableFieldCollection
    {
        $collection = new SearchableFieldCollection();

        $collection->add(new SearchableFieldRecord(
            name: 'name',
            value: $this->name,
            primitive_type: PrimitiveType::STRING,
        ));

        $collection->add(new SearchableFieldRecord(
            name: 'email',
            value: $this->email,
            primitive_type: PrimitiveType::STRING,
        ));

        if ($this->bio) {
            $collection->add(new SearchableFieldRecord(
                name: 'bio',
                value: $this->bio,
                primitive_type: PrimitiveType::STRING,
            ));
        }

        return $collection;
    }

    /**
     * Détermine si l'enregistrement doit être indexé.
     */
    public function shouldBeIndexed(): bool
    {
        return true;
    }

    /**
     * Retourne le Record de formatage personnalisé.
     */
    public function getFuzzyFormat(): ?AbstractRecord
    {
        return null;
    }

    /**
     * Retourne les champs protégés (stop words préservés).
     */
    public function getProtectedFields(): StringTypedCollection
    {
        return new StringTypedCollection();
    }
}
```

### Gestion de l'auto-indexation (optionnelle)

Pour maintenir l'index à jour automatiquement, utilisez les événements Eloquent :

```php
// Dans votre modèle
protected static function booted(): void
{
    static::saved(function ($model) {
        app(IndexService::class)->index($model);
    });

    static::deleted(function ($model) {
        app(IndexService::class)->deleteIndex($model);
    });
}
```

---

## Indexation des données

### Indexation manuelle

```php
use AndyDefer\LaravelSearch\Services\IndexService;

class UserController
{
    public function store(Request $request, IndexService $indexer): JsonResponse
    {
        $user = User::create($request->validated());

        // Indexer manuellement
        $indexer->index($user);

        return response()->json($user, 201);
    }
}
```

### Indexation massive

```php
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// Via le service
$models = new StringTypedCollection();
$models->add(User::class);
$models->add(Product::class);

$stats = $indexer->indexAll($models, force: true);

echo $stats->getValue()->indexed;  // Nombre indexés
echo $stats->getValue()->skipped;  // Nombre ignorés
echo $stats->getValue()->errors;   // Nombre d'erreurs
```

### Réindexation complète

```php
// Supprimer tout l'index
$indexer->clearIndex();

// Réindexer tous les modèles
$indexer->indexAll($models, force: true);
```

---

## Recherche

### Recherche simple

```php
use AndyDefer\LaravelSearch\Services\SearchService;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;

class SearchController
{
    public function search(Request $request, SearchService $search): JsonResponse
    {
        $query = new SearchQueryRecord(
            query: $request->input('q'),
            limit: 10,
            type: $request->input('type'), // Optionnel : filtrer par type de modèle
        );

        $results = $search->search($query);

        return response()->json([
            'results' => $results->toModels(),
            'total' => $results->count(),
        ]);
    }
}
```

### Résultats avec scoring

Chaque résultat retourne :
- `model` : L'instance du modèle trouvé
- `score` : Score brut
- `max_possible` : Score maximum possible
- `percentage` : Pourcentage de pertinence (0-100)

```php
foreach ($results as $result) {
    echo $result->model->name;           // Modèle trouvé
    echo $result->percentage;            // 87.5 (%)
    echo $result->score;                 // Score brut
}
```

### Filtrage par type de modèle

```php
// Rechercher uniquement dans les utilisateurs
$query = new SearchQueryRecord(
    query: 'john',
    limit: 10,
    type: User::class,
);

$results = $search->search($query);
```

### Nettoyage du cache

```php
// Supprimer tout le cache de recherche
$search->clearCache();
```

---

## Cache

### Comment fonctionne le cache ?

Le package utilise `andydefer/jsonl-cache` (PSR-16) pour stocker les résultats de recherche.

- **Clé de cache** : `{prefix}md5(query_limit_type)`
- **Durée de vie** : Configurable via `cache.ttl`
- **Stockage** : Fichiers JSONL dans `storage/jsonl-cache/`

### Configuration du cache

```php
'cache' => [
    'enabled' => true,
    'ttl' => 3600,          // 1 heure
    'prefix' => 'fuzzy_search_',
],
```

### Invalidation automatique

Le cache est automatiquement invalidé lors de :
- L'indexation d'un nouveau modèle
- La mise à jour d'un modèle existant
- La suppression d'un modèle

### Invalidation manuelle

```php
$searchService->clearCache();
```

---

## Commandes CLI

### Installation

```bash
./vendor/bin/directive fuzzy-search-install
```

**Options :**
- `--force` : Forcer l'installation (écrase les fichiers existants)

### Indexation

```bash
# Indexer tous les modèles configurés
./vendor/bin/directive fuzzy-search-index

# Indexer des modèles spécifiques
./vendor/bin/directive fuzzy-search-index User Product

# Forcer la réindexation (écrase les entrées existantes)
./vendor/bin/directive fuzzy-search-index --force

# Avec alias
./vendor/bin/directive fs-index --force
```

### Alias disponibles

| Commande | Alias |
|----------|-------|
| `fuzzy-search-install` | - |
| `fuzzy-search-index` | `fs-index` |

---

## Architecture technique

### Composants principaux

| Composant | Rôle |
|-----------|------|
| `SearchService` | Service principal de recherche |
| `SearchEngineService` | Moteur de recherche (n-grammes, scoring) |
| `IndexService` | Indexation des modèles |
| `NormalizerService` | Normalisation des chaînes (accents, casses) |
| `SearchIndexRepository` | Persistance de l'index |
| `SearchConfig` | Configuration du package |

### Dépendances

```
SearchService
    ├── SearchConfigInterface
    ├── SearchIndexRepository
    ├── SearchEngineServiceInterface
    └── JsonlCacheInterface

SearchEngineService
    ├── SearchEngineConfigContext
    ├── SearchConfigInterface
    ├── JsonlCacheInterface
    └── NormalizerService

IndexService
    ├── SearchConfigInterface
    ├── SearchIndexRepository
    ├── NormalizerService
    └── HydrationService
```

### Flux d'exécution (recherche)

```
1. Recherche
   SearchService::search(SearchQueryRecord)
        ├── Vérification du cache (JsonlCacheInterface)
        ├── Récupération des index (SearchIndexRepository)
        └── Délégation à SearchEngineService

2. Traitement
   SearchEngineService::search(SearchContext)
        ├── Normalisation de la requête (NormalizerService)
        ├── Découpage en mots
        ├── Génération des n-grammes
        ├── Match avec les index
        ├── Calcul du score (pourcentage)
        └── Tri par pertinence

3. Hydratation
   Hydratation des résultats en modèles Eloquent
        └── Retour de SearchResultCollection
```

---

## Tests

### Tester la recherche

```php
<?php

namespace Tests\Feature;

use AndyDefer\LaravelSearch\Records\SearchQueryRecord;
use AndyDefer\LaravelSearch\Services\SearchService;
use App\Models\User;
use Tests\TestCase;

class SearchTest extends TestCase
{
    private SearchService $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = app(SearchService::class);
    }

    public function test_search_returns_results(): void
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $query = new SearchQueryRecord(query: 'john', limit: 10);
        $results = $this->search->search($query);

        $this->assertNotEmpty($results);
        $this->assertEquals('John Doe', $results->first()->model->name);
    }

    public function test_search_with_fuzzy_matching(): void
    {
        User::create(['name' => 'Jonathan', 'email' => 'jon@example.com']);

        $query = new SearchQueryRecord(query: 'jonh', limit: 10);
        $results = $this->search->search($query);

        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0, $results->first()->percentage);
    }
}
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```