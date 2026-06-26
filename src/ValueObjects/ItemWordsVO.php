<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

final class ItemWordsVO extends AbstractValueObject
{
    private SetCollection $words;

    private static ?TextNormalizerService $normalizer = null;

    private static function getNormalizer(): TextNormalizerService
    {
        if (self::$normalizer === null) {
            self::$normalizer = new TextNormalizerService;
        }

        return self::$normalizer;
    }

    public function __construct(string $text)
    {
        $this->words = $this->extractWords($text);
    }

    public static function fromArray(array|Sequential $words): self
    {
        if ($words instanceof Sequential) {
            $words = $words->toArray();
        }

        if (array_keys($words) !== range(0, count($words) - 1)) {
            $words = array_values($words);
        }

        $vo = new self('');
        $vo->words = SetCollection::from($words);

        return $vo;
    }

    private function extractWords(string $text): SetCollection
    {
        $normalizer = self::getNormalizer();
        $words = $normalizer->extractWords($text);

        return SetCollection::from($words);
    }

    public function getValue(): Sequential
    {
        return Sequential::from($this->words->toArray());
    }

    public function toArray(): array
    {
        return $this->words->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->words->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toSetCollection(): SetCollection
    {
        return $this->words;
    }

    public function has(string $word): bool
    {
        return $this->words->contains(strtolower($word));
    }

    public function count(): int
    {
        return $this->words->count();
    }

    public function isEmpty(): bool
    {
        return $this->words->isEmpty();
    }

    public function containsAll(string $text): bool
    {
        $otherWords = new self($text);
        $intersection = $this->words->intersect($otherWords->words);

        return $intersection->count() === $this->words->count();
    }

    public function containsAny(string $text): bool
    {
        $otherWords = new self($text);
        $intersection = $this->words->intersect($otherWords->words);

        return $intersection->isNotEmpty();
    }

    public function union(self $other): self
    {
        $vo = new self('');
        $vo->words = $this->words->union($other->words);

        return $vo;
    }

    public static function fromString(StringVO $text): self
    {
        return new self($text->getValue());
    }

    public static function getTypeName(): string
    {
        return 'item_words';
    }
}
