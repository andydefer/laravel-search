<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Poids des n-grams
    |--------------------------------------------------------------------------
    |
    | Définit le poids de chaque n-gram selon sa longueur.
    | Plus le n-gram est long, plus il est discriminant.
    |
    */
    'gram_weights' => [
        2 => 0.3,
        3 => 0.5,
        4 => 0.7,
        'default' => 1.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ratio de longueur minimum
    |--------------------------------------------------------------------------
    |
    | Pour qu'un mot soit considéré comme candidat, sa longueur doit être
    | au moins égale à ce ratio multiplié par la longueur du mot recherché.
    |
    */
    'min_length_ratio' => 0.6,

    /*
    |--------------------------------------------------------------------------
    | Nombre maximum de candidats
    |--------------------------------------------------------------------------
    |
    | Nombre maximum de candidats conservés après le filtrage initial.
    |
    */
    'max_candidates' => 50,

    /*
    |--------------------------------------------------------------------------
    | Seuil d'arrêt anticipé
    |--------------------------------------------------------------------------
    |
    | Pourcentage du score maximum à partir duquel on arrête le calcul.
    |
    */
    'early_stop_threshold' => 0.95,

    /*
    |--------------------------------------------------------------------------
    | Taille minimum des n-grams
    |--------------------------------------------------------------------------
    |
    | Longueur minimum des n-grams générés.
    |
    */
    'min_ngram_length' => 2,

    /*
    |--------------------------------------------------------------------------
    | Taille maximum des n-grams
    |--------------------------------------------------------------------------
    |
    | Longueur maximum des n-grams générés.
    |
    */
    'max_ngram_length' => 4,

    /*
    |--------------------------------------------------------------------------
    | Pénalité maximum Levenshtein
    |--------------------------------------------------------------------------
    |
    | Pénalité maximum appliquée basée sur la distance Levenshtein.
    |
    */
    'max_penalty' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Mots vides (stop words)
    |--------------------------------------------------------------------------
    |
    | Liste des mots qui ne seront pas indexés.
    |
    */
    'stop_words' => [
        'le', 'la', 'les', 'un', 'une', 'des',
        'et', 'ou', 'mais', 'donc', 'car', 'ni', 'or',
        'pour', 'dans', 'avec', 'sans', 'sous', 'sur',
        'en', 'au', 'aux', 'du', 'de', 'des',
        'ce', 'cet', 'cette', 'ces',
        'mon', 'ton', 'son', 'ma', 'ta', 'sa',
        'mes', 'tes', 'ses', 'nos', 'vos', 'leurs',
    ],
];
