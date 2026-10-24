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
            $table->string('chunk_strategy')->default('size');
            $table->decimal('min_score', 3, 2)->nullable();
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->string('heading_path', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rag_settings', function (Blueprint $table) {
            $table->dropColumn(['chunk_strategy', 'min_score']);
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropColumn('heading_path');
        });
    }
};
