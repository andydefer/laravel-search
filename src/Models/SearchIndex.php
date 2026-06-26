<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Models;

use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\LaravelSearch\ValueObjects\ItemWordsVO;
use AndyDefer\LaravelSearch\ValueObjects\NgramsVO;
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

    public function getItemWords(): ItemWordsVO
    {
        $wordsArray = $this->item_words ?? [];

        if (empty($wordsArray)) {
            return new ItemWordsVO('');
        }

        return ItemWordsVO::fromArray($wordsArray);
    }

    public function getNgrams(): NgramsVO
    {
        $ngramsArray = $this->ngrams ?? [];

        if (empty($ngramsArray)) {
            return new NgramsVO('');
        }

        return NgramsVO::fromArray($ngramsArray);
    }

    // ========== SETTERS ==========

    public function setItemWords(ItemWordsVO $words): self
    {
        $this->item_words = $words->toArray();

        return $this;
    }

    public function setNgrams(NgramsVO $ngrams): self
    {
        $this->ngrams = $ngrams->toArray();

        return $this;
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
            item_words: $this->getItemWords(),
            ngrams: $this->getNgrams(),
        );
    }

    // ========== CRÉATION DEPUIS UN RECORD ==========

    public static function fromRecord(SearchIndexRecord $record): self
    {
        $model = new self;

        if ($record->id !== null) {
            $model->id = $record->id->getValue();
        }

        if ($record->searchable_type !== null) {
            $model->searchable_type = $record->searchable_type->getValue();
        }

        if ($record->searchable_id !== null) {
            $model->searchable_id = $record->searchable_id->getValue();
        }

        if ($record->source_column !== null) {
            $model->source_column = $record->source_column->getValue();
        }

        if ($record->original_text !== null) {
            $model->original_text = $record->original_text->getValue();
        }

        if ($record->item_words !== null) {
            $model->item_words = $record->item_words->toArray();
        }

        if ($record->ngrams !== null) {
            $model->ngrams = $record->ngrams->toArray();
        }

        return $model;
    }
}
