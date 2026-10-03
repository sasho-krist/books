<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('google_id')->unique();
            $table->string('title');
            $table->json('authors')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('published_date', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
