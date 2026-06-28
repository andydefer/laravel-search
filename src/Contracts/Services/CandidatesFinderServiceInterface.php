<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;

interface CandidatesFinderServiceInterface
{
    /**
     * Récupère les candidats depuis la base de données
     */
    public function findCandidates(SearchQueryRecord $query): ItemWordsCollection;
}
