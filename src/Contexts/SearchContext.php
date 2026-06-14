<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contexts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\NormalizedWordRecordCollection;
use AndyDefer\LaravelSearch\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelSearch\Records\NormalizedWordRecord;
use AndyDefer\LaravelSearch\ValueObjects\NormalizedWordVO;
use AndyDefer\LaravelSearch\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelSearch\ValueObjects\SearchResultVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class SearchContext
{
    private SearchQueryVO $query;
    private StringTypedCollection $rawData;
    private NormalizedWordRecordCollection $preprocessedData;
    private SearchResultRecordCollection $results;
    private ?string $error = null;
    private DateTimeVO $startedAt;
    private int $itemsProcessed = 0;

    public function __construct(SearchQueryVO $query)
    {
        $this->query = $query;
        $this->rawData = new StringTypedCollection;
        $this->preprocessedData = new NormalizedWordRecordCollection;
        $this->results = new SearchResultRecordCollection;
        $this->startedAt = new DateTimeVO;
    }

    public function getQuery(): SearchQueryVO
    {
        return $this->query;
    }

    public function getRawData(): StringTypedCollection
    {
        return $this->rawData;
    }

    public function getPreprocessedData(): NormalizedWordRecordCollection
    {
        return $this->preprocessedData;
    }

    public function getResults(): SearchResultRecordCollection
    {
        return $this->results;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getStartedAt(): DateTimeVO
    {
        return $this->startedAt;
    }

    public function getItemsProcessed(): int
    {
        return $this->itemsProcessed;
    }

    public function getDuration(): float
    {
        $now = new DateTimeVO;

        return $now->toTimestamp() - $this->startedAt->toTimestamp();
    }

    public function setData(array $data): void
    {
        $this->rawData = new StringTypedCollection;
        foreach ($data as $item) {
            $this->rawData->add($item);
        }
    }

    public function addPreprocessedItem(string $original, NormalizedWordVO $normalized): void
    {
        $this->preprocessedData->add(new NormalizedWordRecord($original, $normalized));
        $this->itemsProcessed++;
    }

    public function addResult(SearchResultVO $result): void
    {
        $this->results->add($result->getValue());
    }

    public function setError(string $error): void
    {
        $this->error = $error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function hasResults(): bool
    {
        return $this->results->isNotEmpty();
    }

    public function isCompleted(): bool
    {
        return $this->itemsProcessed === $this->rawData->count();
    }
}
