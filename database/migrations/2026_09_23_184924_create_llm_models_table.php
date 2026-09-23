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
        Schema::create('llm_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->string('name');
            $table->string('tier');
            $table->string('provider');
            $table->boolean('active')->default(true);
            $table->decimal('price_input', 10, 4)->nullable();
            $table->decimal('price_cached', 10, 4)->nullable();
            $table->decimal('price_output', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_models');
    }
};
