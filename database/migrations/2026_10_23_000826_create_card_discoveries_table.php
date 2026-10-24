<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('status', 32);
            $table->string('external_agent_id')->nullable();
            $table->string('external_run_id')->nullable();
            $table->text('error_message')->nullable();
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_discoveries');
    }
};
