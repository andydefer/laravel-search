<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_indexes', function (Blueprint $table) {
            // UUID au lieu de auto-increment
            $table->uuid('id')->primary();

            // Champ polymorphique (searchable)
            $table->morphs('searchable');

            // Champ pour stocker le nom de la colonne du modèle source
            $table->string('source_column');

            // Champ pour le texte original
            $table->text('original_text');

            // Champ pour le texte normalisé
            $table->text('normalized_text');

            // Champ pour les mots normalisés (item_words)
            $table->json('item_words');

            // Champ pour les n-grams
            $table->json('ngrams');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_indexes');
    }
};
