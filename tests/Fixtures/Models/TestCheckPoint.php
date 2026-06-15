<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;
use AndyDefer\LaravelSearch\Tests\Fixtures\Records\TestCheckPointFormatRecord;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TestCheckPoint extends Model implements Searchable
{
    use SoftDeletes;

    protected $table = 'test_checkpoints';

    protected $fillable = [
        'name',
        'location',
        'is_active',
        'last_ping_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_ping_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * {@inheritDoc}
     */
    public function getSearchableFields(): SearchableFieldCollection
    {
        $collection = new SearchableFieldCollection;

        $collection->add(new SearchableFieldRecord(
            name: 'name',
            value: $this->name,
            primitive_type: PrimitiveType::STRING,
        ));

        $collection->add(new SearchableFieldRecord(
            name: 'location',
            value: $this->location,
            primitive_type: PrimitiveType::STRING,
        ));

        $collection->add(new SearchableFieldRecord(
            name: 'is_active',
            value: $this->is_active ? 'active' : 'inactive',
            primitive_type: PrimitiveType::STRING,
        ));

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBeIndexed(): bool
    {
        return $this->is_active === true;
    }

    /**
     * {@inheritDoc}
     */
    public function getFuzzyFormat(): ?AbstractRecord
    {
        return new TestCheckPointFormatRecord(
            id: $this->id,
            name: $this->name,
            location: $this->location,
            is_active: $this->is_active,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getProtectedFields(): StringTypedCollection
    {
        return new StringTypedCollection;
    }
}
