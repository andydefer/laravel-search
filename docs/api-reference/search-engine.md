# SearchEngine - Référence Technique

## Description

Moteur de recherche floue (fuzzy search) qui compare des chaînes de caractères en utilisant un système de pondération par sous-chaînes.

## Hiérarchie / Implémentations

```
SearchEngine
    └── Aucune classe parente ou interface (classe autonome)
```

## Rôle principal

Le `SearchEngine` normalise les chaînes (suppression des accents, caractères spéciaux) puis calcule un score de similarité en analysant toutes les sous-chaînes possibles de la requête et en les confrontant aux éléments du jeu de données. Plus une sous-chaîne est longue et présente dans l'élément cible, plus le score est élevé.

**Détails :** [Voir la classe SearchEngine](https://github.com/andydefer/php-fuzzy-search/blob/main/src/SearchEngine.php)

## API / Méthodes publiques

### `__construct(array $data = [])`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<int, string>` | Jeu de données initial à indexer (optionnel) |

**Retourne :** `void`

**Exemple :**
```php
$engine = new SearchEngine(['John Doe', 'Jane Smith', 'Bob Martin']);
```

---

### `setData(array $data): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<int, string>` | Nouveau jeu de données |

**Retourne :** `self` - Retourne l'instance pour le chaînage de méthodes

**Exemple :**
```php
$engine = new SearchEngine();
$engine->setData(['Apple', 'Banana', 'Cherry']);
```

---

### `getData(): array`

**Retourne :** `array<int, string>` - Le jeu de données actuel

**Exemple :**
```php
$engine = new SearchEngine(['John Doe', 'Jane Smith']);
$data = $engine->getData(); // ['John Doe', 'Jane Smith']
```

---

### `search(string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête de recherche |
| `$limit` | `positive-int` | Nombre maximum de résultats à retourner (défaut : 5) |

**Retourne :** `array<int, array{name: string, cleaned_name: string, score: float, max_possible: float, percentage: float}>`

Chaque résultat contient :
- `name` : Le texte original de l'élément
- `cleaned_name` : La version normalisée (sans accents ni caractères spéciaux)
- `score` : Score obtenu par l'élément
- `max_possible` : Score maximum théorique pour cet élément
- `percentage` : Pourcentage de pertinence (`(score * 100) / max_possible`)

**Exemple :**
```php
$engine = new SearchEngine(['John Doe', 'Jon Doe', 'Jane Smith']);
$results = $engine->search('Jon Doe', 2);

foreach ($results as $result) {
    echo $result['name'] . ': ' . $result['percentage'] . "%\n";
}
// John Doe: 82.5%
// Jon Doe: 100%
```

## Cas d'utilisation

### Cas 1 : Recherche de noms avec fautes de frappe

```php
$engine = new SearchEngine([
    'Dr Jean Dupont',
    'Dr Sophie Moreau',
    'Dr Marc Lefèvre'
]);

$results = $engine->search('Marc Lefevre', 3);
// Retourne "Dr Marc Lefèvre" avec un pourcentage élevé malgré l'accent manquant
```

### Cas 2 : Recherche multi-mots avec correspondance partielle

```php
$engine = new SearchEngine([
    'Lucas Leroy',
    'Julie Durand',
    'Lucas Martin'
]);

$results = $engine->search('Lucas', 2);
// Retourne les deux "Lucas" avec priorité au meilleur score
```

### Cas 3 : Recherche dans un catalogue de chansons

```php
$songs = [
    'Bohemian Rhapsody - Queen',
    'Imagine - John Lennon',
    'Hey Jude - The Beatles'
];

$engine = new SearchEngine($songs);
$results = $engine->search('Bohemian Rhapsody', 1);

if (!empty($results)) {
    echo "Meilleur match: " . $results[0]['name'];
}
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Requête vide | Retourne un tableau vide `[]` |
| Jeu de données vide | Retourne un tableau vide `[]` |
| Aucun mot valide après normalisation | Retourne un tableau vide `[]` |

**Note :** La classe ne lève aucune exception. Toutes les situations d'erreur retournent silencieusement un tableau vide.

## Intégration

Le `SearchEngine` est conçu comme une classe autonome sans dépendances externes. Il peut être utilisé :

- **Seul** : Pour des recherches simples dans de petites collections
- **Avec des adaptateurs** : Pour interfacer avec des bases de données
- **Dans des services** : Comme composant de recherche d'un plus grand système

```php
class UserSearchService
{
    private SearchEngine $engine;
    
    public function __construct(array $users)
    {
        $this->engine = new SearchEngine($users);
    }
    
    public function findUsers(string $query): array
    {
        return $this->engine->search($query, 10);
    }
}
```

## Performance

### Complexité algorithmique
- **Prétraitement** : O(n × m²) où n = nombre de mots, m = longueur moyenne des mots
- **Recherche** : O(k × p × q²) où k = nombre de mots dans la requête, p = nombre d'éléments, q = longueur des mots

### Optimisations intégrées
- **Mise en cache implicite** : Aucun cache ; chaque recherche recalcule tout
- **Normalisation** : Effectuée à chaque appel pour garantir la fraîcheur des données
- **Pondération progressive** : Les sous-chaînes longues sont fortement pondérées (`longueur + (longueur-1) × 0.5`)

### Recommandations
- Pour plus de 10 000 éléments, envisagez une indexation préalable
- Pour des recherches fréquentes, mettez en cache les résultats
- Les chaînes longues (+50 caractères) augmentent significativement le temps de calcul

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet (types unions, readonly, etc.) |
| PHP 8.0 | ⚠️ Non testé (utilisation de syntaxe 8.1) |
| PHP 7.4 | ❌ Non supporté (types et syntaxe modernes) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use FuzzySearch\SearchEngine;

// Création d'un jeu de données
$doctors = [
    "Dr Jean Dupont",
    "Dr Sophie Moreau",
    "Dr Marc Lefèvre",
    "Dr Claire Bernard",
    "Dr Antoine Girard"
];

// Initialisation du moteur
$engine = new SearchEngine($doctors);

// Recherche avec faute de frappe
$query = "Marc Lefevre";
$results = $engine->search($query, 3);

// Affichage des résultats
echo "Top 3 résultats pour '$query' :\n";
echo str_repeat("=", 60) . "\n";

foreach ($results as $index => $result) {
    $isPerfect = $result['percentage'] === 100.0 ? " [MATCH PARFAIT]" : "";
    
    echo ($index + 1) . ". " . $result['name'] . "\n";
    echo "   Score: " . $result['score'] . " / " . $result['max_possible'] . "\n";
    echo "   Pertinence: " . $result['percentage'] . "%" . $isPerfect . "\n\n";
}

// Modification du jeu de données
$engine->setData([
    "Nouvel élément 1",
    "Nouvel élément 2"
]);

// Nouvelles recherches
$newResults = $engine->search("Nouvel", 5);
```

## Voir aussi

- **Algorithme de Levenshtein** : Alternative pour la détection de similarité
- **Soundex / Metaphone** : Pour la recherche phonétique (langues européennes)
- **Tokenizer standard** : Pour une approche différente de tokenisation
- **Documentation technique** : `/docs/technical/scoring-algorithm.md` - Détail du calcul des scores
```
---