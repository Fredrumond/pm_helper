<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['feature', 'bug', 'tech_debt', 'spike'])->default('feature');
            $table->text('user_story');
            $table->text('context')->nullable();
            $table->json('acceptance_criteria');
            $table->json('out_of_scope')->nullable();
            $table->text('technical_notes')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('labels')->nullable();
            $table->enum('estimated_complexity', ['XS', 'S', 'M', 'L', 'XL'])->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
