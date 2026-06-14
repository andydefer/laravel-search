# RatingService - Référence Technique

## Description

Service central pour la gestion des notes et avis (ratings) dans Laravel. Permet d'associer des notes de 1 à 5 étoiles à n'importe quel modèle via des relations polymorphiques, avec support des commentaires et métadonnées.

## Hiérarchie

```
RatingService
    └── RatingRepository (via injection)
```

## Rôle principal

Orchestrer toutes les opérations sur les notes : création, mise à jour, suppression, calcul de moyenne, distribution des notes, récupération des notes par noteur ou par modèle noté.

## Installation

```bash
composer require andydefer/laravel-features
```

Le package s'enregistre automatiquement via Laravel auto-discovery.

## API / Méthodes publiques

### `rate(Model $rater, Model $rateable, RatingLevel $rating, ?string $review = null): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle qui note (User, etc.) |
| `$rateable` | `Model` | Modèle noté (Post, Product, etc.) |
| `$rating` | `RatingLevel` | Niveau de note (1 à 5) |
| `$review` | `string|null` | Commentaire optionnel |

**Retourne :** `Model` - La note créée

**Exceptions :** `RuntimeException` - Si le rater a déjà noté ce rateable

**Exemple :**
```php
$rating = $ratingService->rate($user, $post, RatingLevel::FIVE, 'Excellent article !');
```

---

### `updateRating(Model $rater, Model $rateable, RatingLevel $rating, ?string $review = null): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle qui note |
| `$rateable` | `Model` | Modèle noté |
| `$rating` | `RatingLevel` | Nouveau niveau de note |
| `$review` | `string|null` | Nouveau commentaire |

**Retourne :** `Model` - La note mise à jour

**Exceptions :** `RuntimeException` - Si le rater n'a pas encore noté

**Exemple :**
```php
$updated = $ratingService->updateRating($user, $post, RatingLevel::FOUR, 'Mis à jour');
```

---

### `deleteRating(Model $rater, Model $rateable): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle qui note |
| `$rateable` | `Model` | Modèle noté |

**Exceptions :** `RuntimeException` - Si le rater n'a pas noté

**Exemple :**
```php
$ratingService->deleteRating($user, $post);
```

---

### `hasRated(Model $rater, Model $rateable): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle à vérifier |
| `$rateable` | `Model` | Modèle noté à vérifier |

**Retourne :** `bool` - True si une note existe

**Exemple :**
```php
if ($ratingService->hasRated($user, $post)) {
    // Afficher la note existante
}
```

---

### `getRaterRating(Model $rater, Model $rateable): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle qui note |
| `$rateable` | `Model` | Modèle noté |

**Retourne :** `?Model` - La note ou null

**Exemple :**
```php
$rating = $ratingService->getRaterRating($user, $post);
echo $rating->rating_level->getStars();
```

---

### `getRatings(Model $rateable, ?RatingLevel $minRating = null, ?RatingLevel $maxRating = null): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rateable` | `Model` | Modèle noté |
| `$minRating` | `RatingLevel|null` | Note minimale (inclusive) |
| `$maxRating` | `RatingLevel|null` | Note maximale (inclusive) |

**Retourne :** `Collection<Rating>` - Collection des notes

**Exemple :**
```php
// Toutes les notes du post
$allRatings = $ratingService->getRatings($post);

// Notes supérieures ou égales à 4 étoiles
$goodRatings = $ratingService->getRatings($post, RatingLevel::FOUR);
```

---

### `getAverageRating(Model $rateable): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rateable` | `Model` | Modèle noté |

**Retourne :** `float` - Moyenne des notes (arrondie à 2 décimales)

**Exemple :**
```php
$average = $ratingService->getAverageRating($post);
// $average = 4.5
```

---

### `getRatingDistribution(Model $rateable): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rateable` | `Model` | Modèle noté |

**Retourne :** `array` - Distribution des notes par étoile [1 => 0, 2 => 5, ...]

**Exemple :**
```php
$distribution = $ratingService->getRatingDistribution($post);
// [1 => 0, 2 => 3, 3 => 5, 4 => 10, 5 => 12]
```

---

### `getRatingsByRater(Model $rater): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rater` | `Model` | Modèle qui note |

**Retourne :** `Collection<Rating>` - Toutes les notes données par ce rater

**Exemple :**
```php
$userRatings = $ratingService->getRatingsByRater($user);
```

---

### `countRatings(Model $rateable): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rateable` | `Model` | Modèle noté |

**Retourne :** `int` - Nombre total de notes

---

### `countRatingsByLevel(Model $rateable, RatingLevel $level): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$rateable` | `Model` | Modèle noté |
| `$level` | `RatingLevel` | Niveau de note à compter |

**Retourne :** `int` - Nombre de notes pour ce niveau

---

## Cas d'utilisation

### Cas 1 : Système de notation d'articles

```php
final class PostRatingController extends Controller
{
    public function __construct(
        private readonly RatingService $ratingService,
    ) {}

    public function rate(Post $post, Request $request): JsonResponse
    {
        $user = auth()->user();
        $rating = RatingLevel::tryFrom($request->rating);

        if (!$rating) {
            return response()->json(['error' => 'Invalid rating'], 400);
        }

        try {
            $rating = $this->ratingService->rate($user, $post, $rating, $request->review);
            return response()->json($rating, 201);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function update(Post $post, Request $request): JsonResponse
    {
        $user = auth()->user();
        $rating = RatingLevel::tryFrom($request->rating);

        $updated = $this->ratingService->updateRating($user, $post, $rating, $request->review);

        return response()->json($updated);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'average' => $this->ratingService->getAverageRating($post),
            'distribution' => $this->ratingService->getRatingDistribution($post),
            'total' => $this->ratingService->countRatings($post),
        ]);
    }
}
```

### Cas 2 : Filtrer les produits par note minimale

```php
$products = Product::all();
$minRating = RatingLevel::FOUR;

$filtered = $products->filter(function ($product) use ($minRating) {
    return $this->ratingService->getAverageRating($product) >= $minRating->value;
});
```

### Cas 3 : Afficher la note de l'utilisateur

```php
$userRating = $ratingService->getRaterRating(auth()->user(), $post);

if ($userRating) {
    echo "Vous avez noté : " . $userRating->rating_level->getStars();
    echo "Commentaire : " . $userRating->review;
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Note déjà existante (rate) | `RuntimeException` | `{rater_type} {rater_id} has already rated {rateable_type} {rateable_id}` |
| Note inexistante (updateRating) | `RuntimeException` | `{rater_type} {rater_id} has not rated {rateable_type} {rateable_id}` |
| Note inexistante (deleteRating) | `RuntimeException` | `{rater_type} {rater_id} has not rated {rateable_type} {rateable_id}` |

---

## Intégration

### Dans une Action Laravel

```php
use AndyDefer\LaravelFeatures\Ratings\Services\RatingService;

final class RateProductAction extends AbstractAction
{
    public function __construct(
        private readonly RatingService $ratingService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = current_authenticatable();
        $product = Product::findOrFail($request->productId);

        $rating = $this->ratingService->rate($user, $product, $request->rating, $request->review);

        return ResponseFactory::json(RatingData::from($rating), 201);
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `rate()` | O(1) | Insertion unique |
| `updateRating()` | O(1) | Mise à jour par ID |
| `getAverageRating()` | O(n) | Scan de toutes les notes du rateable |
| `getRatingDistribution()` | O(n × 5) | Scan + comptage par niveau |
| `getRatingsByRater()` | O(k) | k = nombre de notes du rater |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |
| Laravel 14.x | ✅ Complet |
| Laravel 15.x | ✅ Complet |

---

## Voir aussi

- `RatingLevel` - Enum des niveaux de note (1 à 5 étoiles)
- `RatingRecord` - Record pour le transport des données
- `RatingRepository` - Repository pour l'accès base de données
- `AddressService` - Service de gestion des adresses
- `LikeService` - Service de gestion des likes