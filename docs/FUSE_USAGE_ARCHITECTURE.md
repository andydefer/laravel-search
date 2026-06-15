# Fuse Usage : Architecture de Recherche Approximative par Pipeline Multi-Étages

## Introduction

La recherche approximative de chaînes de caractères constitue un besoin récurrent dans le développement logiciel. Qu'il s'agisse de correction orthographique, de déduplication de données, ou de moteurs de recherche internes, la capacité à identifier des correspondances proches而非 exactes améliore significativement l'expérience utilisateur.

Fuse Usage est une bibliothèque PHP qui répond à ce besoin. Elle implémente un algorithme de recherche floue basé sur l'analyse de n-grammes pondérés, optimisé pour les environnements aux ressources limitées.

## Problématique Applicative

Dans une application typique de gestion de contacts, un utilisateur peut rechercher "Jean-Pierre Dupont". La base de données peut contenir les variations suivantes :

- "Jean Pierre Dupont"
- "J.P. Dupont"
- "Jean-Pierre Dupond"

Un système de recherche par correspondance exacte ne retournerait aucun résultat. Une solution de recherche approximative est nécessaire.

## Limites des Approches Existantes

### Distance de Levenshtein

Cette méthode calcule le nombre minimal d'opérations (insertion, suppression, substitution) pour transformer une chaîne en une autre. Sa complexité O(m×n) la rend prohibitive pour des corpus de taille importante. Pour 10 000 chaînes de 50 caractères, une recherche nécessite environ 25 millions d'opérations.

### Index Inversé de N-Grammes

Cette approche précalcule tous les sous-ensembles de caractères. Pour un alphabet de 26 lettres et des n-grammes de longueur 4, la table potentielle atteint 456 976 entrées. L'ajout des caractères accentués aggrave cette explosion combinatoire.

### TF-IDF

Conçu initialement pour la recherche documentaire, le TF-IDF nécessite un vocabulaire précalculé et des statistiques globales. Son application à la comparaison de chaînes courtes est disproportionnée.

## Principe Fondamental de Fuse Usage

Fuse Usage implémente un **pipeline de filtrage à trois étages**. Chaque étage élimine les candidats non pertinents avant que l'étage suivant, plus coûteux en calcul, ne soit exécuté.

```
Corpus complet (N éléments)
        ↓
[Étage 1] Filtrage par longueur → Élimine ~40%
        ↓
[Étage 2] Filtrage par lettres → Élimine ~45%
        ↓
[Étage 3] Scoring par n-grammes → Analyse les 15% restants
        ↓
Résultats triés par pertinence
```

Cette architecture réduit la complexité effective de O(N) à O(N × f) où f représente la fraction de candidats survivants (typiquement 0,05 à 0,15).

## Architecture du Système

### Vue d'Ensemble

Le système se compose de six classes principales :

| Classe | Responsabilité |
|--------|----------------|
| `StringNormalizer` | Nettoyage et normalisation des chaînes |
| `NGramEngine` | Génération et pondération des n-grammes |
| `WordComparator` | Comparaison et scoring entre deux mots |
| `PreFilter` | Filtrage rapide par analyse de lettres |
| `DatasetPreprocessor` | Précalcul des structures de données |
| `QueryProcessor` | Orchestration de la recherche |
| `SearchEngine` | Point d'entrée public |

### Description Fonctionnelle

#### StringNormalizer

Cette classe transforme toute chaîne en une forme canonique via trois opérations successives :

1. **Suppression des accents** : table de correspondance couvrant 163 caractères accentués
2. **Suppression des caractères spéciaux** : conservation uniquement des lettres, chiffres, espaces, apostrophes et tirets
3. **Normalisation des espaces** : réduction des espaces multiples et suppression des espaces en début/fin de chaîne

Un cache mémoire associe chaque chaîne brute à sa version nettoyée, évitant les traitements redondants.

#### NGramEngine

Pour un mot donné, cette classe génère l'ensemble des sous-chaînes de longueurs 2, 3 et 4. Par exemple, pour "chat" :

- Longueur 2 : "ch", "ha", "at"
- Longueur 3 : "cha", "hat"
- Longueur 4 : "chat"

Chaque n-gramme reçoit un poids selon sa longueur selon la formule :

```
ω(n) = n + (n - 1) × 0,5 = 1,5n - 0,5
```

| Longueur | Poids |
|----------|-------|
| 2 | 2,5 |
| 3 | 4,5 |
| 4 | 7,0 |

Cette pondération non linéaire privilégie les n-grammes longs, plus discriminants.

#### WordComparator

Cette classe constitue le cœur algorithmique du système. Elle compare deux mots en quatre phases :

**Phase 1 : Filtrage par longueur**
Un mot candidat est éliminé si sa longueur est inférieure à 50% de la longueur du mot requête, ou si l'un des deux mots a moins de 2 caractères.

**Phase 2 : Correspondance de lettres**
Compte le nombre de lettres communes entre les deux mots (en tenant compte des multiplicités). Seuls les candidats avec au moins une lettre commune sont conservés.

**Phase 3 : Sélection des meilleurs candidats**
Les candidats sont triés par nombre de lettres communes décroissant. Seuls les 5 meilleurs sont retenus pour l'analyse détaillée.

**Phase 4 : Scoring détaillé**
Pour chaque n-gramme du mot requête, vérifie sa présence dans le mot candidat et additionne les poids correspondants. L'algorithme s'arrête prématurément si le score atteint 95% du score maximal possible.

#### PreFilter

Applique un filtre rapide sur les items complets (non découpés en mots) avant la comparaison détaillée :

1. Nettoie l'item et la requête
2. Extrait les lettres uniques de chaque chaîne
3. Calcule le pourcentage de lettres de la requête présentes dans l'item
4. Retient l'item si ce pourcentage atteint 30%

Ce filtre élimine environ 70 à 85% des candidats avec un coût de calcul minimal.

#### DatasetPreprocessor

Cette classe précalcule pour chaque item du corpus les structures suivantes :

```
[
    'original' => string,      // Mot original
    'normalized' => string,    // Version normalisée en minuscules
    'max_score' => float,      // Score maximum possible
    'ngrams' => array<int, string>  // N-grammes uniques
]
```

Le précalcul s'effectue une fois lors de l'initialisation. Les recherches suivantes utilisent ces structures précalculées, évitant des traitements redondants.

#### QueryProcessor

Orchestre le déroulement d'une recherche :

1. Nettoie et découpe la requête en mots
2. Pour chaque mot, génère ses n-grammes
3. Pour chaque item, délègue la comparaison mot-à-mot au WordComparator
4. Agrège les scores individuels en un score global
5. Calcule le pourcentage de pertinence moyen
6. Trie les résultats par pertinence décroissante

#### SearchEngine

Classe publique qui encapsule l'ensemble du système. Son API se limite à trois méthodes :

```php
public function __construct(array $data = [])
public function search(string $query, int $limit = 5): array
public function setData(array $data): self
```

## Algorithmes Fondamentaux

### Calcul du Score Maximum

Pour un mot w, le score maximum M(w) est défini comme la somme des poids de tous ses n-grammes uniques :

```
M(w) = Σ_{g ∈ G(w)} ω(|g|)
```

### Calcul du Score de Correspondance

Pour un mot requête q et un mot candidat c, le score S(q,c) est :

```
S(q,c) = Σ_{g ∈ G(q)} [g ⊆ c] × ω(|g|)
```

La notation [g ⊆ c] vaut 1 si le n-gramme g est une sous-chaîne de c, 0 sinon.

### Calcul du Pourcentage de Pertinence

Le pourcentage de pertinence normalise le score par les longueurs respectives des mots :

```
P(q,c) = min(100, round( ((S(q,c) / |q|) × 100) / (M(c) / |c|) , 2))
```

Cette formulation évite que les mots longs ne soient systématiquement favorisés.

### Arrêt Prématuré

L'algorithme interrompt le calcul du score lorsque :

```
S_courant ≥ 0,95 × M(c)
```

Dans ce cas, la correspondance est déjà excellente. Continuer le calcul n'améliorerait pas significativement le résultat final.

## Performances Observées

### Conditions de Test

- Processeur : 2,5 GHz
- Mémoire vive : 8 Go
- Corpus : 10 000 chaînes
- Longueur moyenne : 25 caractères
- Requête : 10 caractères

### Résultats

| Opération | Temps (ms) | Proportion |
|-----------|------------|------------|
| Normalisation requête | 0,15 | 0,5% |
| Pré-filtrage | 8,20 | 27,0% |
| Scoring détaillé | 21,50 | 70,8% |
| Tri | 0,50 | 1,7% |
| **Total** | **30,35** | **100%** |

### Complexité Théorique

- **Temps** : O(N × f × w × g)
- **Mémoire** : O(N × m̄ × ḡ)

Avec :
- N = taille du corpus
- f = fraction de candidats survivants (≈ 0,1)
- w = nombre moyen de mots par item (≈ 5)
- g = nombre moyen de n-grammes par mot (≈ 0,6 × longueur_moyenne)
- m̄ = longueur moyenne des mots
- ḡ = nombre moyen de n-grammes

Pour N = 10 000, le système effectue environ 75 000 opérations par recherche.

## Considérations Pratiques

### Gestion de la Mémoire

Le système implémente trois niveaux de cache :

1. **Cache de normalisation** : associe chaîne brute → chaîne nettoyée
2. **Cache de n-grammes** : associe mot → liste de n-grammes
3. **Cache de scores max** : associe mot → score maximum

Ces caches peuvent être vidés manuellement via `clearCache()` pour libérer la mémoire.

### Limites Connues

| Situation | Comportement | Justification |
|-----------|--------------|---------------|
| Mots < 2 caractères | Ignorés | Pas assez de matière pour une comparaison fiable |
| Transpositions | Non détectées | "ab" et "ba" n'ont pas de n-grammes en commun |
| Corpus dynamique | Réindexation nécessaire | La structure précalculée doit être recréée |

### Sécurité

L'utilisation de `str_contains()` sur des entrées utilisateur ne présente pas de risque d'injection. Les opérations sont purement syntaxiques.

Pour prévenir les attaques par déni de service via requêtes très longues, une limite pratique peut être ajoutée :

```php
private const MAX_QUERY_LENGTH = 200;

public function search(string $query, int $limit = 5): array
{
    if (strlen($query) > self::MAX_QUERY_LENGTH) {
        $query = substr($query, 0, self::MAX_QUERY_LENGTH);
    }
    // Suite du traitement
}
```

## Exemples d'Utilisation

### Recherche dans un Catalogue

```php
use FuzzySearch\SearchEngine;

$catalogue = [
    'iPhone 13 Pro Max 256Go',
    'iPhone 13 Pro 128Go',
    'iPad Pro 12.9 pouces'
];

$engine = new SearchEngine($catalogue);
$resultats = $engine->search('Iphone 13 Pro Max', 3);

foreach ($resultats as $resultat) {
    printf("%s : %s%%\n", $resultat['name'], $resultat['percentage']);
}
```

### Correction Orthographique

```php
class CorrecteurOrthographique
{
    private SearchEngine $engine;
    
    public function __construct(array $dictionnaire)
    {
        $this->engine = new SearchEngine($dictionnaire);
    }
    
    public function suggérer(string $mot, int $limite = 3): array
    {
        return $this->engine->search($mot, $limite);
    }
}

$correcteur = new CorrecteurOrthographique($dictionnaireFrancais);
$suggestions = $correcteur->suggérer('ordinatuer');
```

## Intégration dans une Architecture Logicielle

Fuse Usage peut être intégré à différents niveaux d'une application :

### API REST

```php
#[Route('/search', methods: ['GET'])]
public function search(Request $request): JsonResponse
{
    $query = $request->query->get('q');
    $results = $this->searchEngine->search($query, 10);
    return $this->json($results);
}
```

### Service de Déduplication

```php
class DeduplicationService
{
    public function trouverDoublons(array $entites, float $seuil = 85.0): array
    {
        $engine = new SearchEngine($entites);
        $doublons = [];
        
        foreach ($entites as $entite) {
            $similaires = $engine->search($entite, 5);
            $doublons[$entite] = array_filter($similaires, 
                fn($r) => $r['percentage'] >= $seuil && $r['name'] !== $entite
            );
        }
        
        return $doublons;
    }
}
```

## Conclusion

Fuse Usage propose une solution pratique au problème de la recherche approximative de chaînes. Son architecture en pipeline multi-étages permet d'atteindre des latences de l'ordre de 30 millisecondes pour des corpus de 10 000 entrées, avec une empreinte mémoire inférieure à 10 mégaoctets.

Les choix techniques retenus sont :

- Une pondération non linéaire des n-grammes favorisant les séquences longues
- Un pré-filtrage par analyse de fréquence des lettres éliminant 70 à 85% des candidats
- Un mécanisme d'arrêt prématuré réduisant le travail de calcul de 40%
- Une mise en cache systématique des résultats intermédiaires

La bibliothèque est distribuée sous licence MIT, sans dépendance externe, compatible PHP 8.1 et ultérieur.