<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Collections\MatchResultCollection;
use AndyDefer\LaravelSearch\Collections\QueryWordsCollection;
use AndyDefer\PhpVo\ValueObjects\Types\StringVO;

interface QueryProcessorInterface
{
    public function process(StringVO $query): QueryWordsCollection;

    public function computeScore(QueryWordsCollection $query_words, ItemWordsCollection $item_words): ?MatchResultCollection;

    public function sortResults(MatchResultCollection $results): MatchResultCollection;
}
