<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contexts\NormalizerContext;
use AndyDefer\LaravelSearch\Records\NormalizedDocumentRecord;
use AndyDefer\LaravelSearch\Records\NormalizedFieldRecord;
use AndyDefer\LaravelSearch\Records\SearchableFieldRecord;

final class NormalizerService
{
    public function __construct(
        private readonly NormalizerContext $context,
    ) {}

    public function normalizeString(string $string): string
    {
        if ($this->context->has($string)) {
            return $this->context->getNormalized($string);
        }

        $normalized = $this->context->removeAccents($string);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9\s\'-]/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        $this->context->setNormalized($string, $normalized);

        return $normalized;
    }

    public function normalizeField(
        SearchableFieldRecord $field,
        StringTypedCollection $protectedFields
    ): SearchableFieldRecord {
        $isProtected = $protectedFields->contains($field->name);

        if ($isProtected) {
            return $field;
        }

        return new SearchableFieldRecord(
            name: $field->name,
            value: $this->normalizeString($field->value),
            primitive_type: $field->primitive_type,
        );
    }

    public function normalizeCollection(
        SearchableFieldCollection $collection,
        StringTypedCollection $protectedFields
    ): SearchableFieldCollection {
        $normalized = new SearchableFieldCollection;

        foreach ($collection as $field) {
            $normalized->add($this->normalizeField($field, $protectedFields));
        }

        return $normalized;
    }

    /**
     * NE PLUS UTILISER - Déprécié
     *
     * @deprecated Utilisez buildNormalizedDocument() à la place
     */
    public function buildContentString(SearchableFieldCollection $fields): string
    {
        $values = [];
        foreach ($fields as $field) {
            $values[] = $field->value;
        }

        return implode(' ', $values);
    }

    /**
     * Construit un document normalisé avec structure champ/valeur
     */
    public function buildNormalizedDocument(
        SearchableFieldCollection $fields,
        string $searchableType,
        string $searchableId
    ): NormalizedDocumentRecord {
        $normalizedFields = new SearchableFieldCollection;

        foreach ($fields as $field) {
            $normalizedFields->add(new NormalizedFieldRecord(
                name: $field->name,
                originalValue: $field->value,
                normalizedValue: $this->normalizeString($field->value),
                primitiveType: $field->primitive_type,
            ));
        }

        return new NormalizedDocumentRecord(
            searchable_type: $searchableType,
            searchable_id: $searchableId,
            fields: $normalizedFields,
        );
    }

    /**
     * Recherche dans un champ spécifique uniquement
     */
    public function searchInField(
        NormalizedDocumentRecord $document,
        string $fieldName,
        string $query
    ): float {
        foreach ($document->fields as $field) {
            if ($field->name === $fieldName) {
                return $this->calculateFieldScore($field->normalizedValue, $query);
            }
        }

        return 0.0;
    }

    /**
     * Recherche dans tous les champs avec pondération
     */
    public function searchAcrossFields(
        NormalizedDocumentRecord $document,
        string $query,
        array $fieldWeights = []
    ): float {
        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($document->fields as $field) {
            $weight = $fieldWeights[$field->name] ?? 1.0;
            $fieldScore = $this->calculateFieldScore($field->normalizedValue, $query);

            $totalScore += $fieldScore * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalScore / $totalWeight : 0.0;
    }

    private function calculateFieldScore(string $fieldValue, string $query): float
    {
        $normalizedQuery = $this->normalizeString($query);

        if (str_contains($fieldValue, $normalizedQuery)) {
            return 1.0;
        }

        // Score basé sur les n-grammes
        $queryLength = strlen($normalizedQuery);
        $fieldLength = strlen($fieldValue);

        $matches = 0;
        for ($i = 0; $i <= $fieldLength - $queryLength; $i++) {
            if (substr($fieldValue, $i, $queryLength) === $normalizedQuery) {
                $matches++;
            }
        }

        return $matches > 0 ? min(0.5 + ($matches * 0.1), 1.0) : 0.0;
    }

    public function getContext(): NormalizerContext
    {
        return $this->context;
    }
}
