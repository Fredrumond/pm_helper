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
        Schema::table('card_discoveries', function (Blueprint $table) {
            $table->json('pending_answers')->nullable()->after('document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_discoveries', function (Blueprint $table) {
            $table->dropColumn('pending_answers');
        });
    }
};
