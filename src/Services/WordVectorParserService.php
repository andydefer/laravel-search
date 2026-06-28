<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;
use AndyDefer\LaravelSearch\Contracts\Services\NgramInterface;
use AndyDefer\LaravelSearch\Contracts\Services\TextNormalizerInterface;
use AndyDefer\LaravelSearch\Contracts\Services\WordVectorParserInterface;
use AndyDefer\LaravelSearch\Records\WordVectorRecord;

final class WordVectorParserService implements WordVectorParserInterface
{
    public function __construct(
        private readonly TextNormalizerInterface $normalizer,
        private readonly NgramInterface $ngram,
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

            $normalized = $this->normalizer->normalize($word ?? '');

            $unique_letters = new StringTypedCollection;
            foreach (array_unique(str_split($normalized)) as $letter) {
                $unique_letters->add($letter);
            }

            $metaphone = metaphone($normalized);

            $ngrams = $this->ngram->generate($normalized)->toArray();
            $bigrams = new StringTypedCollection;
            foreach (array_values(array_filter($ngrams, fn ($g) => strlen($g) === 2)) as $bigram) {
                $bigrams->add($bigram);
            }

            $metaphone_bigrams = new StringTypedCollection;
            $metaphoneLength = strlen($metaphone);
            for ($i = 0; $i < $metaphoneLength - 1; $i++) {
                $metaphone_bigrams->add(substr($metaphone, $i, 2));
            }

            $collection->add(WordVectorRecord::from([
                'word' => $word,
                'metaphone' => $metaphone,
                'unique_letters' => $unique_letters,
                'bigrams' => $bigrams,
                'metaphone_bigrams' => $metaphone_bigrams,
            ]));
        }

        return $collection;
    }

    public function unparse(WordVectorCollection $collection): StringTypedCollection
    {
        $result = [];
        foreach ($collection as $record) {
            $params = [
                'metaphone' => $record->metaphone,
                'unique_letters' => $record->unique_letters->toArray(),
                'bigrams' => $record->bigrams->toArray(),
                'metaphone_bigrams' => $record->metaphone_bigrams->toArray(),
            ];

            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $result[] = $record->word.'?'.$query;
        }

        return StringTypedCollection::from($result);
    }

    public function parseUrisToCollection(array $uris): WordVectorCollection
    {
        $collection = new WordVectorCollection;

        foreach ($uris as $uri) {
            $parts = explode('?', $uri);
            $word = $parts[0];
            $queryString = $parts[1] ?? '';

            parse_str($queryString, $decoded);

            $unique_letters = new StringTypedCollection;
            foreach ($decoded['unique_letters'] ?? [] as $letter) {
                $unique_letters->add($letter);
            }

            $bigrams = new StringTypedCollection;
            foreach ($decoded['bigrams'] ?? [] as $bigram) {
                $bigrams->add($bigram);
            }

            $metaphone_bigrams = new StringTypedCollection;
            foreach ($decoded['metaphone_bigrams'] ?? [] as $bigram) {
                $metaphone_bigrams->add($bigram);
            }

            $collection->add(WordVectorRecord::from([
                'word' => $word,
                'metaphone' => $decoded['metaphone'] ?? '',
                'unique_letters' => $unique_letters,
                'bigrams' => $bigrams,
                'metaphone_bigrams' => $metaphone_bigrams,
            ]));
        }

        return $collection;
    }

    public function unparseCollectionToUris(WordVectorCollection $collection): StringTypedCollection
    {
        return $this->unparse($collection);
    }
}
