<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\WordVectorCollection;

interface WordVectorParserInterface
{
    public function parse(array $wordsArray): WordVectorCollection;

    public function unparse(WordVectorCollection $collection): StringTypedCollection;

    public function parseUrisToCollection(array $uris): WordVectorCollection;

    public function unparseCollectionToUris(WordVectorCollection $collection): StringTypedCollection;
}
