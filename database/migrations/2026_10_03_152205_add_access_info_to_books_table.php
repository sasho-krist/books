<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Google's accessInfo.viewability: NO_PAGES, PARTIAL, ALL_PAGES or UNKNOWN.
            // Null means the book was saved before this was tracked.
            $table->string('viewability', 20)->nullable()->after('published_date');
            $table->boolean('embeddable')->default(false)->after('viewability');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['viewability', 'embeddable']);
        });
    }
};
