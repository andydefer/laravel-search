<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Records\WordVectorRecord;

final class WordVectorParserService
{
    public function __construct(
        private readonly TextNormalizerService $normalizer,
        private readonly NgramService $ngramService,
    ) {}

    public function parse(array $wordsArray): WordVectorCollection
    {
        if (empty($wordsArray)) {
            return new WordVectorCollection;
        }

        $collection = new WordVectorCollection;

        foreach ($wordsArray as $wordUri) {
            $parts = explode('?', $wordUri);
            $word = $parts[0];

            $normalized = $this->normalizer->normalize($word);

            $uniqueLetters = new StringTypedCollection;
            foreach (array_unique(str_split($normalized)) as $letter) {
                $uniqueLetters->add($letter);
            }

            $metaphone = metaphone($normalized);

            $ngrams = $this->ngramService->generate($normalized)->toArray();
            $bigrams = new StringTypedCollection;
            foreach (array_values(array_filter($ngrams, fn ($g) => strlen($g) === 2)) as $bigram) {
                $bigrams->add($bigram);
            }

            $metaphoneBigrams = new StringTypedCollection;
            $metaphoneLength = strlen($metaphone);
            for ($i = 0; $i < $metaphoneLength - 1; $i++) {
                $metaphoneBigrams->add(substr($metaphone, $i, 2));
            }

            $collection->add(WordVectorRecord::from([
                'word' => $word,
                'metaphone' => $metaphone,
                'unique_letters' => $uniqueLetters,
                'bigrams' => $bigrams,
                'metaphone_bigrams' => $metaphoneBigrams,
            ]));
        }

        return $collection;
    }

    public function unparse(WordVectorCollection $collection): array
    {
        $result = [];
        foreach ($collection as $record) {
            $params = [
                'metaphone' => $record->metaphone,
                'unique_letters' => $record->uniqueLetters->toArray(),
                'bigrams' => $record->bigrams->toArray(),
                'metaphone_bigrams' => $record->metaphoneBigrams->toArray(),
            ];

            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $result[] = $record->word.'?'.$query;
        }

        return $result;
    }
}
