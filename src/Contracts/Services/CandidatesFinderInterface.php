<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\LaravelSearch\Collections\ItemWordsCollection;
use AndyDefer\LaravelSearch\Records\SearchQueryRecord;

interface CandidatesFinderInterface
{
    /**
     * Récupère les candidats depuis la base de données
     * Retourne une collection de SearchIndexRecord
     */
    public function findCandidates(SearchQueryRecord $query): ItemWordsCollection;
}
