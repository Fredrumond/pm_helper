<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->string('step')->nullable()->after('model');
            $table->index('step');
        });
    }

    public function down(): void
    {
        Schema::table('llm_usages', function (Blueprint $table) {
            $table->dropIndex(['step']);
            $table->dropColumn('step');
        });
    }
};
