<?php

namespace Tests\Unit\Services;

use App\Models\Card;
use App\Services\Agents\DiscoveryPromptBuilder;
use Tests\TestCase;

class DiscoveryPromptBuilderTest extends TestCase
{
    public function test_prompt_includes_the_card_the_skill_and_the_read_only_override(): void
    {
        $card = new Card([
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
        ]);

        $prompt = (new DiscoveryPromptBuilder)->build($card);

        $this->assertStringContainsString('Avisar card parecido', $prompt);
        $this->assertStringContainsString('Evitar demanda duplicada.', $prompt);
        $this->assertStringContainsString('- Mostrar até 3 cards parecidos.', $prompt);
        $this->assertStringContainsString('Somente leitura', $prompt);
        $this->assertStringContainsString('NÃO faça commit', $prompt);
        $this->assertStringContainsString('# Skill: Discovery', $prompt);
        $this->assertStringNotContainsString('CURSOR_API_KEY', $prompt);
    }
}
