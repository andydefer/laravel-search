<?php

declare(strict_types=1);

namespace AndyDefer\LaravelSearch\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\LaravelSearch\Models\SearchIndex;
use AndyDefer\LaravelSearch\Records\SearchIndexFiltersRecord;
use AndyDefer\LaravelSearch\Records\SearchIndexRecord;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SearchIndexRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(SearchIndex::class, SearchIndexRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof SearchIndexFiltersRecord) {
            return;
        }

        if ($filters->searchable_type !== null) {
            $query->where('searchable_type', $filters->searchable_type);
        }

        if ($filters->searchable_id !== null) {
            $query->where('searchable_id', $filters->searchable_id);
        }

        if ($filters->content !== null) {
            $query->where('content', 'like', '%' . $filters->content . '%');
        }

        if ($filters->normalized_content !== null) {
            $query->where('normalized_content', 'like', '%' . $filters->normalized_content . '%');
        }

        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('content', 'like', '%' . $filters->search . '%')
                    ->orWhere('normalized_content', 'like', '%' . $filters->search . '%');
            });
        }
    }

    /**
     * Vérifie si les filtres ont au moins un critère non nul
     */
    private function hasAnyFilter(SearchIndexFiltersRecord $filters): bool
    {
        return $filters->searchable_type !== null
            || $filters->searchable_id !== null
            || $filters->content !== null
            || $filters->normalized_content !== null
            || $filters->search !== null;
    }

    /**
     * Crée ou met à jour un enregistrement d'index
     */
    public function updateOrCreate(SearchIndexRecord $record): SearchIndex
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $record->searchable_type,
            searchable_id: $record->searchable_id,
        );

        $findBy = new FindByRecord(
            filters: $filters,
        );

        $existing = $this->findBy($findBy)->first();

        if ($existing) {
            $existing->update($record->toArrayWithoutNulls());
            return $existing;
        }

        $modelClass = $this->info()->modelClass;
        $model = new $modelClass;
        $model->fill($record->toArrayWithoutNulls())->save();

        return $model;
    }

    /**
     * Supprime les enregistrements correspondant aux filtres.
     * Ne supprime rien si aucun filtre n'est fourni (sécurité).
     */
    public function deleteBulk(AbstractRecord $criteria): int
    {
        if (! $criteria instanceof SearchIndexFiltersRecord) {
            return 0;
        }

        if (! $this->hasAnyFilter($criteria)) {
            return 0;
        }

        return parent::deleteBulk($criteria);
    }

    /**
     * Supprime définitivement les enregistrements correspondant aux filtres.
     * Ne supprime rien si aucun filtre n'est fourni (sécurité).
     */
    public function forceDeleteBulk(AbstractRecord $criteria): int
    {
        if (! $criteria instanceof SearchIndexFiltersRecord) {
            return 0;
        }

        if (! $this->hasAnyFilter($criteria)) {
            return 0;
        }

        return parent::forceDeleteBulk($criteria);
    }

    /**
     * Supprime tous les enregistrements de l'index
     */
    public function deleteAll(): int
    {
        return $this->buildQuery(new EmptyRecord())->delete();
    }

    /**
     * Supprime définitivement tous les enregistrements de l'index
     */
    public function forceDeleteAll(): int
    {
        $query = $this->buildQuery(new EmptyRecord());

        if ($this->usesSoftDeletes()) {
            return $query->forceDelete();
        }

        return $query->delete();
    }

    /**
     * Vérifie si le modèle utilise SoftDeletes
     */
    private function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass));
    }

    /**
     * Compte le nombre total d'enregistrements dans l'index
     */
    public function countAll(): int
    {
        return $this->buildQuery(new EmptyRecord())->count();
    }

    /**
     * Vérifie si l'index contient un enregistrement pour un modèle donné
     */
    public function existsForModel(string $searchableType, string $searchableId): bool
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $searchableType,
            searchable_id: $searchableId,
        );

        return $this->exists($filters);
    }

    /**
     * Récupère l'enregistrement d'index pour un modèle donné
     */
    public function findByModel(string $searchableType, string $searchableId): ?SearchIndex
    {
        $filters = new SearchIndexFiltersRecord(
            searchable_type: $searchableType,
            searchable_id: $searchableId,
        );

        $findBy = new FindByRecord(filters: $filters);

        return $this->findBy($findBy)->first();
    }
}
