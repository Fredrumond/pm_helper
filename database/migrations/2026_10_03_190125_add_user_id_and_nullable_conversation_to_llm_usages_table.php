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
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });

        Schema::table('llm_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable()->change();
        });

        Schema::table('llm_usages', function (Blueprint $table) {
            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropForeign(['conversation_id']);
        });

        Schema::table('llm_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable(false)->change();
        });

        Schema::table('llm_usages', function (Blueprint $table) {
            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();
        });
    }
};
