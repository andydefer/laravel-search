<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Models;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\Services\WordVectorParserService;
use AndyDefer\PhpVo\ValueObjects\Strings\UuidVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;
use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    protected $table = 'search_indexes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'searchable_type',
        'searchable_id',
        'normalized_text',
        'source_column',
        'original_text',
        'item_words',
        'ngrams',
    ];

    protected $casts = [
        'item_words' => 'array',
        'ngrams' => 'array',
    ];

    public function searchable()
    {
        return $this->morphTo();
    }

    // ========== GETTERS ==========

    public function getId(): UuidVO
    {
        return UuidVO::from($this->id);
    }

    public function getSearchableType(): StringVO
    {
        return StringVO::from($this->searchable_type);
    }

    public function getSearchableId(): StringVO
    {
        return StringVO::from((string) $this->searchable_id);
    }

    public function getSourceColumn(): StringVO
    {
        return StringVO::from($this->source_column);
    }

    public function getOriginalText(): StringVO
    {
        return StringVO::from($this->original_text);
    }

    public function getNormalizedText(): StringVO
    {
        return StringVO::from($this->normalized_text);
    }

    public function getItemWords(): WordVectorCollection
    {
        /** @var WordVectorParserService $parser */
        $parser = app(WordVectorParserService::class);

        return $parser->parse($this->item_words ?? []);
    }

    public function getNgrams(): StringTypedCollection
    {
        $ngramsArray = $this->ngrams ?? [];

        if (empty($ngramsArray)) {
            return StringTypedCollection::from([]);
        }

        return StringTypedCollection::from($ngramsArray);
    }

    // ========== TO RECORD ==========

    public function toRecord(): SearchIndexRecord
    {
        return new SearchIndexRecord(
            id: $this->getId(),
            searchable_type: $this->getSearchableType(),
            searchable_id: $this->getSearchableId(),
            source_column: $this->getSourceColumn(),
            original_text: $this->getOriginalText(),
            normalized_text: $this->getNormalizedText(),
            item_words: $this->getItemWords(),
            ngrams: $this->getNgrams(),
        );
    }
}
