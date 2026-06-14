<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Enums;

enum TestUserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::USER => 'Utilisateur',
            self::GUEST => 'Invité',
        };
    }
}
