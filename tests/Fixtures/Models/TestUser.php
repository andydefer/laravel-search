<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;
use AndyDefer\LaravelSearch\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelSearch\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model implements Searchable
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'age',
        'metadata',
    ];

    protected $casts = [
        'status' => TestUserStatus::class,
        'role' => TestUserRole::class,
        'metadata' => 'array',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }

    /**
     * {@inheritDoc}
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

        if ($this->role !== null) {
            $collection->add(new SearchableFieldRecord(
                name: 'role',
                value: $this->role->value,
                primitive_type: PrimitiveType::STRING,
            ));
        }

        if ($this->status !== null) {
            $collection->add(new SearchableFieldRecord(
                name: 'status',
                value: $this->status->value,
                primitive_type: PrimitiveType::STRING,
            ));
        }

        if ($this->age !== null) {
            $collection->add(new SearchableFieldRecord(
                name: 'age',
                value: (string) $this->age,
                primitive_type: PrimitiveType::INT,
            ));
        }

        if ($this->metadata !== null && is_array($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $collection->add(new SearchableFieldRecord(
                        name: "metadata.{$key}",
                        value: (string) $value,
                        primitive_type: PrimitiveType::STRING,
                    ));
                }
            }
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBeIndexed(): bool
    {
        return $this->status !== TestUserStatus::INACTIVE && $this->status !== TestUserStatus::BANNED;
    }

    /**
     * {@inheritDoc}
     */
    public function getFuzzyFormat(): ?AbstractRecord
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getProtectedFields(): StringTypedCollection
    {
        $collection = new StringTypedCollection();

        // Les emails sont protégés (stop words préservés)
        $collection->add('email');

        return $collection;
    }
}
