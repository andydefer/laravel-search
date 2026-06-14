<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->morphs('searchable');
            $table->longText('content');
            $table->longText('normalized_content')->nullable();
            $table->json('fields')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('search_index');
    }
};
