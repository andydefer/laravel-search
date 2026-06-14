# CommentService - Référence Technique

## Description

Service central pour la gestion des commentaires hiérarchiques dans Laravel. Permet d'associer des commentaires à n'importe quel modèle via des relations polymorphiques, avec support des réponses (threads), modération (publié, masqué, signalé) et métadonnées.

## Hiérarchie

```
CommentService
    └── CommentRepository (via injection)
```

## Rôle principal

Orchestrer toutes les opérations sur les commentaires : création, mise à jour, suppression, modération (publier/masquer/signaler), récupération hiérarchique, comptage et gestion des réponses.

## Installation

```bash
composer require andydefer/laravel-features
```

Le package s'enregistre automatiquement via Laravel auto-discovery.

## API / Méthodes publiques

### `add(Model $commenter, Model $commentable, string $content, ?int $parentId = null): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commenter` | `Model` | Modèle qui commente (User, etc.) |
| `$commentable` | `Model` | Modèle commenté (Post, Product, etc.) |
| `$content` | `string` | Contenu du commentaire |
| `$parentId` | `int|null` | ID du commentaire parent (pour les réponses) |

**Retourne :** `Model` - Le commentaire créé (statut PUBLISHED par défaut)

**Exemple :**
```php
// Commentaire simple
$comment = $commentService->add($user, $post, 'Super article !');

// Réponse à un commentaire existant
$reply = $commentService->add($user, $post, 'Merci !', $comment->id);
```

---

### `update(int $commentId, string $content): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à modifier |
| `$content` | `string` | Nouveau contenu |

**Retourne :** `Model` - Le commentaire mis à jour

**Exceptions :** `RuntimeException` - Si le commentaire n'existe pas

**Exemple :**
```php
$updated = $commentService->update($commentId, 'Contenu corrigé');
```

---

### `delete(int $commentId): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à supprimer |

**Exceptions :** `RuntimeException` - Si le commentaire n'existe pas

**Exemple :**
```php
$commentService->delete($commentId);
```

---

### `hide(int $commentId): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à masquer |

**Retourne :** `Model` - Le commentaire masqué (status HIDDEN)

**Exemple :**
```php
$hidden = $commentService->hide($commentId);
```

---

### `publish(int $commentId): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à publier |

**Retourne :** `Model` - Le commentaire publié (status PUBLISHED)

**Exemple :**
```php
$published = $commentService->publish($commentId);
```

---

### `flag(int $commentId): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à signaler |

**Retourne :** `Model` - Le commentaire signalé (status FLAGGED)

**Exemple :**
```php
$flagged = $commentService->flag($commentId);
```

---

### `get(Model $commentable, bool $onlyPublished = true): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentable` | `Model` | Modèle commenté |
| `$onlyPublished` | `bool` | Filtrer uniquement les commentaires publiés (défaut: true) |

**Retourne :** `Collection<Comment>` - Tous les commentaires du modèle

**Exemple :**
```php
// Tous les commentaires publiés
$comments = $commentService->get($post);

// Tous les commentaires (y compris masqués/signalés)
$allComments = $commentService->get($post, false);
```

---

### `getReplies(int $parentId, bool $onlyPublished = true): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parentId` | `int` | ID du commentaire parent |
| `$onlyPublished` | `bool` | Filtrer uniquement les réponses publiées |

**Retourne :** `Collection<Comment>` - Les réponses au commentaire parent

**Exemple :**
```php
$replies = $commentService->getReplies($commentId);
```

---

### `find(int $commentId): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentId` | `int` | ID du commentaire à rechercher |

**Retourne :** `?Model` - Le commentaire trouvé ou null

---

### `getByCommenter(Model $commenter): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commenter` | `Model` | Modèle qui commente |

**Retourne :** `Collection<Comment>` - Tous les commentaires de cet utilisateur

**Exemple :**
```php
$userComments = $commentService->getByCommenter($user);
```

---

### `count(Model $commentable, bool $onlyPublished = true): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$commentable` | `Model` | Modèle commenté |
| `$onlyPublished` | `bool` | Compter uniquement les commentaires publiés |

**Retourne :** `int` - Nombre de commentaires

---

### `countFlagged(): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | Aucun paramètre |

**Retourne :** `int` - Nombre total de commentaires signalés

---

### `countHidden(): int`

**Retourne :** `int` - Nombre total de commentaires masqués

---

### `countPublished(): int`

**Retourne :** `int` - Nombre total de commentaires publiés

---

## Cas d'utilisation

### Cas 1 : Système de commentaires d'article

```php
final class PostCommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    public function store(Post $post, Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $comment = $this->commentService->add($user, $post, $request->content);
        
        return response()->json($comment, 201);
    }

    public function reply(Post $post, int $parentId, Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $reply = $this->commentService->add($user, $post, $request->content, $parentId);
        
        return response()->json($reply, 201);
    }

    public function index(Post $post): JsonResponse
    {
        $comments = $this->commentService->get($post);
        
        // Organiser les commentaires avec leurs réponses
        $tree = $this->buildCommentTree($comments);
        
        return response()->json([
            'comments' => $tree,
            'total' => $this->commentService->count($post),
        ]);
    }

    private function buildCommentTree(Collection $comments): array
    {
        $grouped = [];
        
        foreach ($comments as $comment) {
            if ($comment->parent_id === null) {
                $grouped[$comment->id] = [
                    'comment' => $comment,
                    'replies' => $this->commentService->getReplies($comment->id),
                ];
            }
        }
        
        return array_values($grouped);
    }
}
```

### Cas 2 : Modération des commentaires

```php
final class CommentModerationController extends Controller
{
    public function moderate(int $commentId, string $action): JsonResponse
    {
        $comment = $this->commentService->find($commentId);
        
        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }
        
        $result = match ($action) {
            'publish' => $this->commentService->publish($commentId),
            'hide' => $this->commentService->hide($commentId),
            'flag' => $this->commentService->flag($commentId),
            default => throw new \InvalidArgumentException('Invalid action'),
        };
        
        return response()->json($result);
    }
    
    public function stats(): JsonResponse
    {
        return response()->json([
            'published' => $this->commentService->countPublished(),
            'hidden' => $this->commentService->countHidden(),
            'flagged' => $this->commentService->countFlagged(),
        ]);
    }
}
```

### Cas 3 : Commentaires utilisateur

```php
public function userComments(User $user): JsonResponse
{
    $comments = $this->commentService->getByCommenter($user);
    
    return response()->json([
        'comments' => $comments,
        'total' => $comments->count(),
    ]);
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Commentaire non trouvé (update) | `RuntimeException` | `Comment {id} not found` |
| Commentaire non trouvé (delete) | `RuntimeException` | `Comment {id} not found` |
| Commentaire non trouvé (hide/publish/flag) | `RuntimeException` | `Comment {id} not found` |

---

## Intégration

### Dans une Action Laravel

```php
use AndyDefer\LaravelFeatures\Comments\Services\CommentService;

final class AddCommentAction extends AbstractAction
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = current_authenticatable();
        $post = Post::findOrFail($request->postId);

        $comment = $this->commentService->add($user, $post, $request->content);

        return ResponseFactory::json(CommentData::from($comment), 201);
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `add()` | O(1) | Insertion unique |
| `update()` | O(1) | Mise à jour par ID |
| `delete()` | O(1) | Suppression par ID |
| `get()` | O(n) | n = nombre de commentaires |
| `getReplies()` | O(k) | k = nombre de réponses |
| `count()` | O(1) | Requête COUNT optimisée |

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

- `CommentStatus` - Enum des statuts (published, hidden, flagged)
- `CommentRecord` - Record pour le transport des données
- `CommentRepository` - Repository pour l'accès base de données
- `AddressService` - Service de gestion des adresses
- `LikeService` - Service de gestion des likes
- `RatingService` - Service de gestion des notes