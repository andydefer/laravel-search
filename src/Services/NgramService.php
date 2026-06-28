<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelSearch\Configs\SearchConfig;

final class NgramService
{
    public function __construct(
        private readonly SearchConfig $config,
        private readonly TextNormalizerService $normalizer,
    ) {}

    public function generate(string $word): SetCollection
    {
        $normalized = $this->normalizer->normalize($word);
        $length = strlen($normalized);
        $grams = SetCollection::from([]);

        $minLength = $this->config->getMinNgramLength();
        $maxLength = $this->config->getMaxNgramLength();

        for ($gramLength = $minLength; $gramLength <= $maxLength; $gramLength++) {
            if ($gramLength > $length) {
                continue;
            }

            for ($i = 0; $i <= $length - $gramLength; $i++) {
                $grams = $grams->add(substr($normalized, $i, $gramLength));
            }
        }

        return $grams;
    }

    public function generateFromWords(array $words): array
    {
        $result = [];
        foreach ($words as $word) {
            $result[$word] = $this->generate($word)->toArray();
        }

        return $result;
    }

    public function generateFromText(string $text): SetCollection
    {
        $words = $this->normalizer->extractWords($text);
        $allGrams = SetCollection::from([]);

        foreach ($words as $word) {
            $grams = $this->generate($word);
            foreach ($grams->toArray() as $gram) {
                $allGrams = $allGrams->add($gram);
            }
        }

        return $allGrams;
    }
}
