<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelSearch\Collections\SearchableFieldCollection;
use AndyDefer\LaravelSearch\Contexts\NormalizerContext;
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

    public function buildContentString(SearchableFieldCollection $fields): string
    {
        $values = [];
        foreach ($fields as $field) {
            $values[] = $field->value;
        }

        return implode(' ', $values);
    }

    public function getContext(): NormalizerContext
    {
        return $this->context;
    }
}
