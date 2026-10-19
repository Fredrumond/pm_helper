<?php

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
        Schema::table('rag_settings', function (Blueprint $table) {
            $table->json('sync_sources')->nullable()->after('min_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rag_settings', function (Blueprint $table) {
            $table->dropColumn('sync_sources');
        });
    }
};
