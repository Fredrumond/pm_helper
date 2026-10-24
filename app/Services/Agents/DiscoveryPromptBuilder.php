<?php

namespace App\Services\Agents;

use App\Models\Card;

class DiscoveryPromptBuilder
{
    public function build(Card $card): string
    {
        $path = base_path('resources/agent_skills/discovery/SKILL.md');
        $skill = is_readable($path) ? file_get_contents($path) : false;
        if (! is_string($skill) || trim($skill) === '') {
            throw new AgentProviderException('missing_skill');
        }

        $cardBlock = <<<CARD
## Demanda (card do PM Helper)

Título: {$card->title}
Objetivo: {$this->text($card->objetivo)}
Como funciona hoje (segundo o PM): {$this->text($card->como_funciona_hoje)}
Prioridade: {$card->priorityLabel()}
Regras:
{$this->lines($card->regras)}
Onde (segundo o PM):
{$this->lines($card->onde)}
Critérios de aceite:
{$this->lines($card->aceite)}
O que não fazer:
{$this->lines($card->o_que_nao_fazer)}
Stakeholders:
{$this->lines($card->stakeholders)}
Como validar:
{$this->text($card->como_validar)}
CARD;

        return <<<PROMPT
Use a skill de discovery abaixo nesta codebase. Explore o código real do repositório para embasar o documento.

OVERRIDE OBRIGATÓRIO (Cloud Agent via API — prevalece sobre a skill):
- Somente leitura. NÃO edite, crie ou apague arquivos. NÃO faça commit, push nem abra PR.
- NÃO grave em docs/discovery/ (nem em qualquer outro path do repo).
- Devolva o documento Markdown completo do Discovery na resposta final (a API só captura esse texto).
- Pode e deve citar arquivos/classes reais do repositório nas seções pertinentes.
- Responda em português.

{$cardBlock}

---

# Skill de discovery (catálogo do PM Helper)

{$skill}
PROMPT;
    }

    private function text(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '(não informado)';
    }

    /**
     * @param  array<int, mixed>|null  $items
     */
    private function lines(?array $items): string
    {
        $lines = [];
        foreach ($items ?? [] as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $lines[] = '- '.$text;
            }
        }

        return $lines === [] ? '- (não informado)' : implode("\n", $lines);
    }
}
