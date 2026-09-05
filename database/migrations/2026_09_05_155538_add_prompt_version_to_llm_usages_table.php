<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->string('prompt_version')->nullable()->after('model');
            $table->string('prompt_hash', 64)->nullable()->after('prompt_version');

            $table->index('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->dropIndex(['prompt_version']);
            $table->dropColumn(['prompt_version', 'prompt_hash']);
        });
    }
};
