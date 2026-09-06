<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'user_story',
                'context',
                'acceptance_criteria',
                'out_of_scope',
                'technical_notes',
                'labels',
                'estimated_complexity',
            ]);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->text('objetivo')->nullable();
            $table->text('como_funciona_hoje')->nullable();
            $table->json('regras')->nullable();
            $table->json('onde')->nullable();
            $table->json('aceite')->nullable();
            $table->json('o_que_nao_fazer')->nullable();
            $table->json('stakeholders')->nullable();
            $table->text('como_validar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'objetivo',
                'como_funciona_hoje',
                'regras',
                'onde',
                'aceite',
                'o_que_nao_fazer',
                'stakeholders',
                'como_validar',
            ]);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->enum('type', ['feature', 'bug', 'tech_debt', 'spike'])->default('feature');
            $table->text('user_story');
            $table->text('context')->nullable();
            $table->json('acceptance_criteria');
            $table->json('out_of_scope')->nullable();
            $table->text('technical_notes')->nullable();
            $table->json('labels')->nullable();
            $table->enum('estimated_complexity', ['XS', 'S', 'M', 'L', 'XL'])->nullable();
        });
    }
};
