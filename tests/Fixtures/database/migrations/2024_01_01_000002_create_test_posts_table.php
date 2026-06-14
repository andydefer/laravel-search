<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the test_posts table for package testing.
 *
 * This table is used exclusively by the test suite to verify polymorphic
 * relationships and other Eloquent features with a realistic model.
 * It includes foreign key reference to test_users and soft deletes support.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * Creates the test_posts table with user foreign key, title, body,
     * timestamps, and soft delete support.
     */
    public function up(): void
    {
        Schema::create('test_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops the test_posts table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_posts');
    }
};
