<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // 'google' or 'chitanka'. For Chitanka, google_id holds "chitanka-{book|text}-{id}".
            $table->string('source', 20)->default('google')->after('google_id');
            // Where to read the book online (Chitanka) and the download links by format.
            $table->string('source_url', 500)->nullable()->after('source');
            $table->json('downloads')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_url', 'downloads']);
        });
    }
};
