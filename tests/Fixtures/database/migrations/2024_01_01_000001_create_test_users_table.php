<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->nullable();
            $table->string('role')->nullable();
            $table->integer('age')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes pour optimiser les recherches
            $table->index('name');
            $table->index('email');
            $table->index('status');
            $table->index('role');
            $table->index('age');
            $table->index(['status', 'role']); // Index composite courant
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_users');
    }
};
