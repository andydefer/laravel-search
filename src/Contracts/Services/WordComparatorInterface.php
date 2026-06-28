<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Records\ItemWordRecord;
use AndyDefer\LaravelSearch\Records\MatchResultRecord;
use AndyDefer\LaravelSearch\Records\QueryWordRecord;
use AndyDefer\PhpVo\ValueObjects\Types\FloatVO;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

interface WordComparatorInterface
{
    public function countMatchingLetters(StringVO $word1, StringVO $word2): int;

    public function passesLengthFilter(StringVO $query_word, StringVO $item_word): bool;

    public function calculateScore(QueryWordRecord $query_data, ItemWordRecord $item_data): FloatVO;

    public function findBestMatch(QueryWordRecord $query_data, ItemWordsCollection $item_words): MatchResultRecord;
}
