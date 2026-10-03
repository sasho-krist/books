<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // ISO 639-1 code from Google (volumeInfo.language), e.g. "bg", "ru", "en".
            $table->string('language', 10)->nullable()->after('published_date');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
