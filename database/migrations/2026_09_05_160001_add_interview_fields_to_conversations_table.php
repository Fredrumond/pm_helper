<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('prompt_name')->nullable()->after('status');
            $table->string('current_step')->default('interview')->after('prompt_version');
            $table->text('interview_summary')->nullable()->after('current_step');
            $table->index('prompt_name');
            $table->index('current_step');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['prompt_name']);
            $table->dropIndex(['current_step']);
            $table->dropColumn(['prompt_name', 'current_step', 'interview_summary']);
        });
    }
};
