<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;
use AndyDefer\LaravelSearch\Services\TextNormalizerService;

final class NgramsVO extends AbstractValueObject
{
    private SetCollection $ngrams;

    private static ?TextNormalizerService $normalizer = null;

    private static function getNormalizer(): TextNormalizerService
    {
        if (self::$normalizer === null) {
            self::$normalizer = new TextNormalizerService;
        }

        return self::$normalizer;
    }

    public function __construct(string $word, ?SearchConfig $config = null)
    {
        $config = $config ?? new SearchConfig;
        $this->ngrams = $this->generate($word, $config);
    }

    public static function fromArray(array|Sequential $ngrams): self
    {
        if ($ngrams instanceof Sequential) {
            $ngrams = $ngrams->toArray();
        }

        if (array_keys($ngrams) !== range(0, count($ngrams) - 1)) {
            $ngrams = array_values($ngrams);
        }

        $vo = new self('');
        $vo->ngrams = SetCollection::from($ngrams);

        return $vo;
    }

    private function generate(string $word, SearchConfig $config): SetCollection
    {
        $normalizer = self::getNormalizer();
        $word = $normalizer->normalize($word);
        $length = strlen($word);
        $grams = SetCollection::from([]);

        $minLength = $config->getMinNgramLength();
        $maxLength = $config->getMaxNgramLength();

        for ($gramLength = $minLength; $gramLength <= $maxLength; $gramLength++) {
            if ($gramLength > $length) {
                continue;
            }

            for ($i = 0; $i <= $length - $gramLength; $i++) {
                $grams = $grams->add(substr($word, $i, $gramLength));
            }
        }

        return $grams;
    }

    public function getValue(): Sequential
    {
        return Sequential::from($this->ngrams->toArray());
    }

    public function toJson(): string
    {
        return json_encode($this->ngrams->toArray(), JSON_THROW_ON_ERROR);
    }

    public function toSetCollection(): SetCollection
    {
        return $this->ngrams;
    }

    public function toArray(): array
    {
        return $this->ngrams->toArray();
    }

    public function has(string $ngram): bool
    {
        return $this->ngrams->contains($ngram);
    }

    public function count(): int
    {
        return $this->ngrams->count();
    }

    public function isEmpty(): bool
    {
        return $this->ngrams->isEmpty();
    }

    public static function getTypeName(): string
    {
        return 'ngrams';
    }
}
