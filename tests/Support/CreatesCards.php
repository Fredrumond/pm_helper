<?php

namespace Tests\Support;

use App\Models\Card;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;

trait CreatesCards
{
    protected function createCard(User $user, ?Project $project, string $status = 'approved', array $overrides = []): Card
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
            'project_id' => $project?->id,
        ]);

        return Card::query()->create(array_merge([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Avisar card parecido',
            'objetivo' => 'Evitar demanda duplicada.',
            'como_funciona_hoje' => 'O card é salvo sem comparação.',
            'regras' => ['Comparar no mesmo projeto.'],
            'onde' => ['Tela do card'],
            'aceite' => ['Mostrar até 3 cards parecidos.'],
            'o_que_nao_fazer' => ['Não bloquear a geração.'],
            'stakeholders' => ['PM'],
            'como_validar' => 'Gerar dois cards parecidos.',
            'priority' => 'high',
            'status' => $status,
        ], $overrides));
    }
}
