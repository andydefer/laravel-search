# AddressService - Référence Technique

## Description

Service central pour la gestion des adresses morphées dans Laravel. Permet d'associer des adresses à n'importe quel modèle Eloquent (Users, Clinics, Doctors, etc.) avec support des types d'adresses, coordonnées géographiques et métadonnées.

## Hiérarchie

```
AddressService
    └── AddressRepository (via injection)
```

## Rôle principal

Orchestrer toutes les opérations CRUD sur les adresses : création, mise à jour, suppression, récupération par type, gestion de l'adresse primaire, et comptage. Il sert de façade unique pour interagir avec le module d'adresses.

## Installation

```bash
composer require andydefer/laravel-features
```

Le package s'enregistre automatiquement via Laravel auto-discovery.

## API / Méthodes publiques

### `add(Model $addressable, AddressRecord $record): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle auquel associer l'adresse (User, Clinic, etc.) |
| `$record` | `AddressRecord` | Record contenant les données de l'adresse |

**Retourne :** `Model` - L'adresse créée (instance de `Address`)

**Exemple :**
```php
$address = $addressService->add($user, AddressRecord::from([
    'street' => '123 Main St',
    'city' => 'Paris',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('75001'),
    'address_type' => AddressType::PRIMARY,
]));
```

---

### `update(int $addressId, AddressRecord $record): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressId` | `int` | ID de l'adresse à modifier |
| `$record` | `AddressRecord` | Record avec les champs à mettre à jour |

**Retourne :** `Model` - L'adresse mise à jour

**Exemple :**
```php
$updated = $addressService->update($addressId, AddressRecord::from([
    'street' => '456 New St',
    'city' => 'Lyon',
]));
```

---

### `updateRaw(int $addressId, array $data): Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressId` | `int` | ID de l'adresse à modifier |
| `$data` | `array<string, mixed>` | Tableau brut de données (supporte les valeurs `null`) |

**Retourne :** `Model` - L'adresse mise à jour

**Exemple :**
```php
// Mettre à null le champ metadata
$updated = $addressService->updateRaw($addressId, [
    'metadata' => null,
]);
```

---

### `delete(int $addressId): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressId` | `int` | ID de l'adresse à supprimer |

**Retourne :** `bool` - True si supprimé, false sinon

**Exemple :**
```php
$deleted = $addressService->delete($addressId);
```

---

### `all(Model $addressable): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle dont on veut les adresses |

**Retourne :** `Collection<int, Address>` - Collection de toutes les adresses du modèle

**Exemple :**
```php
$addresses = $addressService->all($user);
foreach ($addresses as $address) {
    echo $address->street;
}
```

---

### `byType(Model $addressable, AddressType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle dont on veut les adresses |
| `$type` | `AddressType` | Type d'adresse (PRIMARY, BILLING, SHIPPING, WORK, OTHER) |

**Retourne :** `Collection<int, Address>` - Adresses du type spécifié

**Exemple :**
```php
$billingAddresses = $addressService->byType($user, AddressType::BILLING);
$shippingAddresses = $addressService->byType($user, AddressType::SHIPPING);
```

---

### `primary(Model $addressable): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle dont on veut l'adresse primaire |

**Retourne :** `?Model` - L'adresse primaire ou null si aucune

**Exemple :**
```php
$primary = $addressService->primary($user);
if ($primary) {
    echo $primary->street;
}
```

---

### `setPrimary(Model $addressable, int $addressId): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle concerné |
| `$addressId` | `int` | ID de l'adresse à définir comme primaire |

**Retourne :** `void`

**Exemple :**
```php
$addressService->setPrimary($user, $addressId);
// L'ancienne adresse primaire devient OTHER
// La nouvelle devient PRIMARY
```

---

### `find(int $addressId): ?Model`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressId` | `int` | ID de l'adresse à rechercher |

**Retourne :** `?Model` - L'adresse trouvée ou null

**Exemple :**
```php
$address = $addressService->find($addressId);
```

---

### `count(Model $addressable): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle dont on veut compter les adresses |

**Retourne :** `int` - Nombre d'adresses associées

**Exemple :**
```php
$count = $addressService->count($user);
```

---

### `hasType(Model $addressable, AddressType $type): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$addressable` | `Model` | Modèle à vérifier |
| `$type` | `AddressType` | Type d'adresse à vérifier |

**Retourne :** `bool` - True si au moins une adresse du type existe

**Exemple :**
```php
if ($addressService->hasType($user, AddressType::BILLING)) {
    // Afficher le formulaire de facturation
}
```

---

## Cas d'utilisation

### Cas 1 : Créer un utilisateur avec son adresse

```php
$user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

$address = $addressService->add($user, AddressRecord::from([
    'street' => '123 Main St',
    'city' => 'Paris',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('75001'),
    'address_type' => AddressType::PRIMARY,
]));
```

### Cas 2 : Gérer plusieurs adresses par utilisateur

```php
// Ajouter une adresse de livraison
$addressService->add($user, AddressRecord::from([
    'street' => '456 Shipping Ln',
    'city' => 'Lyon',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('69001'),
    'address_type' => AddressType::SHIPPING,
]));

// Ajouter une adresse de facturation
$addressService->add($user, AddressRecord::from([
    'street' => '789 Billing Ave',
    'city' => 'Marseille',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('13001'),
    'address_type' => AddressType::BILLING,
]));

// Récupérer les adresses par type
$billingAddresses = $addressService->byType($user, AddressType::BILLING);
```

### Cas 3 : Changer l'adresse primaire

```php
// Définir une nouvelle adresse primaire
$addressService->setPrimary($user, $newAddressId);

// L'ancienne adresse primaire est automatiquement passée à OTHER
$oldPrimary = $addressService->primary($user); // Nouvelle adresse
```

### Cas 4 : Ajouter des coordonnées géographiques

```php
$coordinates = CoordinatesVO::from([
    'latitude' => 48.8566,
    'longitude' => 2.3522,
]);

$addressService->add($user, AddressRecord::from([
    'street' => 'Tour Eiffel',
    'city' => 'Paris',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('75007'),
    'address_type' => AddressType::OTHER,
    'geo_coordinates' => $coordinates,
]));
```

### Cas 5 : Ajouter des métadonnées

```php
$metadata = StrictDataObject::from([
    'floor' => 3,
    'building' => 'Tower A',
    'intercom' => '1234',
]);

$addressService->add($user, AddressRecord::from([
    'street' => '123 Main St',
    'city' => 'Paris',
    'country' => Country::FR,
    'postal_code' => PostalCodeVO::from('75001'),
    'address_type' => AddressType::PRIMARY,
    'metadata' => $metadata,
]));
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Adresse non trouvée (update) | `ModelNotFoundException` | `Address with id {id} not found` |
| Adresse non trouvée (delete) | Aucune (retourne false) | - |
| Adresse non trouvée (find) | Aucune (retourne null) | - |
| Champ requis manquant | `InvalidArgumentException` | Levée par `AddressRecord::from()` |

---

## Intégration

### Dans une Action Laravel

```php
use AndyDefer\LaravelFeatures\Addresses\Services\AddressService;

final class CreateUserAddressAction extends AbstractAction
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = current_authenticatable();
        
        $address = $this->addressService->add($user, $request);
        
        return ResponseFactory::json(AddressData::from($address), 201);
    }
}
```

### Dans un Service Provider

```php
use AndyDefer\LaravelFeatures\Addresses\Services\AddressService;

$this->app->singleton(AddressService::class);
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `add()` | O(1) | Insertion unique |
| `update()` | O(1) | Mise à jour par ID |
| `all()` | O(n) | n = nombre d'adresses |
| `byType()` | O(k) | k = nombre d'adresses du type |
| `primary()` | O(1) | LIMIT 1 |
| `setPrimary()` | O(2) | Deux mises à jour |

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

## Exemple complet

```php
use AndyDefer\LaravelFeatures\Addresses\Services\AddressService;
use AndyDefer\LaravelFeatures\Addresses\Enums\AddressType;
use AndyDefer\LaravelFeatures\Addresses\Records\AddressRecord;
use AndyDefer\PhpVo\Enums\Country;
use AndyDefer\PhpVo\ValueObjects\PostalCodeVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class UserAddressManager
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    public function addUserAddress(User $user, array $data): Address
    {
        $metadata = StrictDataObject::from($data['metadata'] ?? []);
        
        $record = AddressRecord::from([
            'street' => $data['street'],
            'city' => $data['city'],
            'country' => Country::from($data['country']),
            'postal_code' => PostalCodeVO::from($data['postal_code']),
            'address_type' => AddressType::from($data['type']),
            'metadata' => $metadata,
        ]);
        
        return $this->addressService->add($user, $record);
    }
    
    public function getUserPrimaryAddress(User $user): ?Address
    {
        return $this->addressService->primary($user);
    }
    
    public function switchPrimaryAddress(User $user, int $newPrimaryId): void
    {
        $this->addressService->setPrimary($user, $newPrimaryId);
    }
    
    public function getUserAddressesByType(User $user, AddressType $type): Collection
    {
        return $this->addressService->byType($user, $type);
    }
}
```

---

## Voir aussi

- `AddressRecord` - Record pour le transport des données
- `AddressRepository` - Repository pour l'accès base de données
- `AddressType` - Enum des types d'adresse
- `CoordinatesVO` - Value Object pour les coordonnées
- `PostalCodeVO` - Value Object pour les codes postaux
---