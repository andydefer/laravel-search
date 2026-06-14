# LikeService - Référence Technique

## Description

Service central pour la gestion des likes et réactions dans Laravel. Permet d'associer des likes à n'importe quel modèle via des relations polymorphiques, avec support de différents types de réactions (like, love, haha, wow, sad, angry).

## Hiérarchie

```
LikeService
    └── LikeRepository (via injection)
```

## Rôle principal

Orchestrer toutes les opérations sur les likes : ajout, suppression, toggle, comptage par type, récupération des likeurs et filtrage par date. Il sert de façade unique pour interagir avec le module de likes.

## Installation

```bash
composer require andydefer/laravel-features
```

Le package s'enregistre automatiquement via Laravel auto-discovery.

## API / Méthodes publiques

### `toggle(Model $liker, Model $likeable, LikeType $type = LikeType::LIKE): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle qui like (User, etc.) |
| `$likeable` | `Model` | Modèle liké (Post, Comment, etc.) |
| `$type` | `LikeType` | Type de réaction (défaut: LIKE) |

**Retourne :** `bool` - True si like ajouté, False si like supprimé

**Comportement :**
- Like inexistant → ajoute et retourne true
- Like existant avec même type → supprime et retourne false
- Like existant avec type différent → met à jour le type et retourne true

**Exemple :**
```php
// Ajoute un like
$liked = $likeService->toggle($user, $post, LikeType::LIKE);

// Supprime le like (toggle off)
$liked = $likeService->toggle($user, $post, LikeType::LIKE);

// Change le type de like en love
$liked = $likeService->toggle($user, $post, LikeType::LOVE);
```

---

### `like(Model $liker, Model $likeable): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle qui like |
| `$likeable` | `Model` | Modèle liké |

**Exceptions :** `RuntimeException` - Si le like existe déjà

**Exemple :**
```php
$likeService->like($user, $post);
```

---

### `unlike(Model $liker, Model $likeable): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle qui unlike |
| `$likeable` | `Model` | Modèle liké |

**Exceptions :** `RuntimeException` - Si le like n'existe pas

**Exemple :**
```php
$likeService->unlike($user, $post);
```

---

### `hasLiked(Model $liker, Model $likeable): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle à vérifier |
| `$likeable` | `Model` | Modèle liké à vérifier |

**Retourne :** `bool` - True si le like existe

**Exemple :**
```php
if ($likeService->hasLiked($user, $post)) {
    // Afficher "Vous avez liké"
}
```

---

### `countLikes(Model $likeable): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$likeable` | `Model` | Modèle dont on veut compter les likes |

**Retourne :** `int` - Nombre total de likes

**Exemple :**
```php
$totalLikes = $likeService->countLikes($post);
```

---

### `countLikesByType(Model $likeable, LikeType $type): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$likeable` | `Model` | Modèle dont on veut compter les likes |
| `$type` | `LikeType` | Type de réaction à compter |

**Retourne :** `int` - Nombre de likes du type spécifié

**Exemple :**
```php
$loveCount = $likeService->countLikesByType($post, LikeType::LOVE);
```

---

### `getLikers(Model $likeable): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$likeable` | `Model` | Modèle dont on veut les likeurs |

**Retourne :** `Collection<Model>` - Collection des likeurs

**Exemple :**
```php
$likers = $likeService->getLikers($post);
foreach ($likers as $liker) {
    echo $liker->name;
}
```

---

### `getLikersByType(Model $likeable, LikeType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$likeable` | `Model` | Modèle dont on veut les likeurs |
| `$type` | `LikeType` | Type de réaction à filtrer |

**Retourne :** `Collection<Model>` - Likeurs ayant utilisé ce type

**Exemple :**
```php
$loveLikers = $likeService->getLikersByType($post, LikeType::LOVE);
```

---

### `getLikerLikes(Model $liker): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle dont on veut les likes |

**Retourne :** `Collection<Like>` - Likes envoyés par le likeur

**Exemple :**
```php
$userLikes = $likeService->getLikerLikes($user);
```

---

### `getLikerLikesByType(Model $liker, LikeType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle dont on veut les likes |
| `$type` | `LikeType` | Type de réaction à filtrer |

**Retourne :** `Collection<Like>` - Likes du type spécifique envoyés par le likeur

---

### `getLikesUpdatedAfter(DateTimeVO $date): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$date` | `DateTimeVO` | Date seuil |

**Retourne :** `Collection<Like>` - Likes mis à jour après la date

---

### `getLikerLikesUpdatedAfter(Model $liker, DateTimeVO $date): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$liker` | `Model` | Modèle likeur |
| `$date` | `DateTimeVO` | Date seuil |

**Retourne :** `Collection<Like>` - Likes du likeur mis à jour après la date

---

### `getLikesForLikeableUpdatedAfter(Model $likeable, DateTimeVO $date): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$likeable` | `Model` | Modèle liké |
| `$date` | `DateTimeVO` | Date seuil |

**Retourne :** `Collection<Like>` - Likes du likeable mis à jour après la date

---

## Cas d'utilisation

### Cas 1 : Système de likes simple

```php
use AndyDefer\LaravelFeatures\Likes\Services\LikeService;

final class PostController extends Controller
{
    public function __construct(
        private readonly LikeService $likeService,
    ) {}

    public function like(Post $post): JsonResponse
    {
        $user = auth()->user();
        
        try {
            $this->likeService->like($user, $post);
            return response()->json(['liked' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'Already liked'], 400);
        }
    }
    
    public function unlike(Post $post): JsonResponse
    {
        $user = auth()->user();
        
        try {
            $this->likeService->unlike($user, $post);
            return response()->json(['liked' => false]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'Not liked'], 400);
        }
    }
}
```

### Cas 2 : Système de réactions multiples

```php
final class ReactionController extends Controller
{
    public function react(Post $post, string $reaction): JsonResponse
    {
        $user = auth()->user();
        $type = LikeType::tryFrom($reaction);
        
        if (!$type) {
            return response()->json(['error' => 'Invalid reaction'], 400);
        }
        
        $result = $this->likeService->toggle($user, $post, $type);
        
        return response()->json([
            'liked' => $result,
            'type' => $type->value,
            'emoji' => $type->getEmoji(),
        ]);
    }
    
    public function stats(Post $post): JsonResponse
    {
        $stats = [];
        
        foreach (LikeType::cases() as $type) {
            $stats[$type->value] = [
                'count' => $this->likeService->countLikesByType($post, $type),
                'emoji' => $type->getEmoji(),
            ];
        }
        
        return response()->json([
            'total' => $this->likeService->countLikes($post),
            'by_type' => $stats,
        ]);
    }
}
```

### Cas 3 : Filtrer les likes récents

```php
public function recentLikes(Post $post): JsonResponse
{
    $lastWeek = DateTimeVO::from(now()->subWeek()->toIso8601String());
    
    $recentLikes = $this->likeService->getLikesForLikeableUpdatedAfter($post, $lastWeek);
    
    return response()->json($recentLikes);
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Like déjà existant (méthode like) | `RuntimeException` | `User {id} has already liked {type} {id}` |
| Like inexistant (méthode unlike) | `RuntimeException` | `User {id} has not liked {type} {id}` |

---

## Intégration

### Dans une Action Laravel

```php
use AndyDefer\LaravelFeatures\Likes\Services\LikeService;

final class ToggleLikeAction extends AbstractAction
{
    public function __construct(
        private readonly LikeService $likeService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = current_authenticatable();
        $post = Post::findOrFail($request->postId);
        
        $result = $this->likeService->toggle($user, $post, $request->type);
        
        return ResponseFactory::json([
            'liked' => $result,
            'count' => $this->likeService->countLikes($post),
        ]);
    }
}
```

### Dans un Service Provider

```php
$this->app->singleton(LikeService::class);
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `like()` / `unlike()` | O(1) | Insertion ou suppression unique |
| `toggle()` | O(1) | Une recherche + une insertion/suppression/mise à jour |
| `countLikes()` | O(1) | Requête COUNT optimisée |
| `getLikers()` | O(n) | n = nombre de likeurs |

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

- `LikeType` - Enum des types de réactions
- `LikeRecord` - Record pour le transport des données
- `LikeRepository` - Repository pour l'accès base de données
- `DateTimeVO` - Value Object pour les dates
---