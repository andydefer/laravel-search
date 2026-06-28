<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Contracts\Services;

use AndyDefer\DomainStructures\Utils\SetCollection;

interface NgramInterface
{
    /**
     * Génère les n-grams pour un mot donné
     *
     * @param  string  $word  Le mot à transformer en n-grams
     * @return SetCollection Collection des n-grams générés
     */
    public function generate(string $word): SetCollection;

    /**
     * Génère les n-grams pour plusieurs mots
     *
     * @param  array<string>  $words  Liste des mots
     * @return array<string, array<string>> Tableau associatif mot => n-grams
     */
    public function generateFromWords(array $words): array;

    /**
     * Génère les n-grams à partir d'un texte complet
     *
     * @param  string  $text  Le texte à analyser
     * @return SetCollection Collection unique de tous les n-grams
     */
    public function generateFromText(string $text): SetCollection;
}
