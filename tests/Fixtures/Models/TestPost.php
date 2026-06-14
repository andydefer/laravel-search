<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contracts\Searchable;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestPost extends Model implements Searchable
{
    protected $table = 'test_posts';

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    /**
     * {@inheritDoc}
     */
    public function getSearchableFields(): SearchableFieldCollection
    {
        $collection = new SearchableFieldCollection();

        $collection->add(new SearchableFieldRecord(
            name: 'title',
            value: $this->title,
            primitive_type: PrimitiveType::STRING,
        ));

        $collection->add(new SearchableFieldRecord(
            name: 'body',
            value: $this->body,
            primitive_type: PrimitiveType::STRING,
        ));

        if ($this->user_id !== null) {
            $collection->add(new SearchableFieldRecord(
                name: 'user_id',
                value: (string) $this->user_id,
                primitive_type: PrimitiveType::INT,
            ));
        }

        if ($this->user !== null) {
            $collection->add(new SearchableFieldRecord(
                name: 'user_name',
                value: $this->user->name,
                primitive_type: PrimitiveType::STRING,
            ));
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBeIndexed(): bool
    {
        return $this->title !== null && $this->title !== '';
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
        return new StringTypedCollection();
    }
}
