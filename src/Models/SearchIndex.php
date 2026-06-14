<?php

namespace AndyDefer\LaravelSearch\Models;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    public $timestamps = true;

    protected $table = 'search_index';

    protected $fillable = [
        'searchable_type',
        'searchable_id',
        'content',
        'normalized_content',
        'fields',
    ];

    protected $casts = [
        'searchable_id' => 'string',
        'fields' => 'array',
    ];

    private HydrationService $hydration;

    public function __construct(array $attributes = [], ?HydrationService $hydration = null)
    {
        parent::__construct($attributes);
        $this->hydration = $hydration ?? new HydrationService;
    }

    public function searchable()
    {
        return $this->morphTo();
    }

    public function getFields(): SearchableFieldCollection
    {
        $fields = $this->fields ?? [];

        if (empty($fields)) {
            return new SearchableFieldCollection;
        }

        if (is_string($fields)) {
            $fields = json_decode($fields, true) ?? [];
        }

        return $this->hydration->collect($fields, SearchableFieldCollection::class);
    }
}
