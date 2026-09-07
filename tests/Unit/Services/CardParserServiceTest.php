<?php

namespace Tests\Unit\Services;

use App\Services\CardParserService;
use Tests\TestCase;

class CardParserServiceTest extends TestCase
{
    public function test_extracts_interview_summary_and_strips_signal_tags(): void
    {
        $parser = new CardParserService;
        $content = <<<'TXT'
Podemos gerar o card.

<INTERVIEW_COMPLETE>
<INTERVIEW_SUMMARY>
Problema: checkout sem pagamento
</INTERVIEW_SUMMARY>
</INTERVIEW_COMPLETE>
TXT;

        $this->assertTrue($parser->hasInterviewComplete($content));
        $this->assertSame(
            'Problema: checkout sem pagamento',
            $parser->extractInterviewSummary($content)
        );
        $this->assertSame('Podemos gerar o card.', $parser->extractTextOnly($content));
    }

    public function test_returns_null_summary_when_block_is_missing(): void
    {
        $parser = new CardParserService;

        $this->assertFalse($parser->hasInterviewComplete('Ainda estou perguntando.'));
        $this->assertNull($parser->extractInterviewSummary('Ainda estou perguntando.'));
    }

    public function test_detects_natural_language_interview_ready(): void
    {
        $parser = new CardParserService;
        $content = 'Perfeito, tenho tudo que preciso. Esse resumo reflete o que você tem em mente? Se sim, a entrevista está fechada e você já pode gerar o card no produto.';

        $this->assertTrue($parser->looksInterviewReady($content));
        $this->assertTrue($parser->hasInterviewComplete($content));
    }

    public function test_detects_entrevista_finalizada_as_interview_ready(): void
    {
        $parser = new CardParserService;
        $content = '### Resumo da entrevista\n\nLanding page e Stripe.\n\nEntrevista finalizada! O botão "Gerar Card" deve aparecer agora no produto.';

        $this->assertTrue($parser->looksInterviewReady($content));
        $this->assertTrue($parser->hasInterviewComplete($content));
    }

    public function test_detects_and_strips_interview_scope_too_broad_tag(): void
    {
        $parser = new CardParserService;
        $content = <<<'TXT'
Isso não cabe em um único card. Volte com o card mais definido.

<INTERVIEW_SCOPE_TOO_BROAD></INTERVIEW_SCOPE_TOO_BROAD>
TXT;

        $this->assertTrue($parser->hasInterviewScopeTooBroad($content));
        $this->assertFalse($parser->hasInterviewComplete($content));
        $this->assertSame(
            'Isso não cabe em um único card. Volte com o card mais definido.',
            $parser->extractTextOnly($content)
        );
    }

    public function test_detects_scope_too_broad_tag_variants(): void
    {
        $parser = new CardParserService;
        $prefix = 'Isso não cabe em um único card.';
        $variants = [
            '<INTERVIEW_SCOPE_TOO_BROAD></INTERVIEW_SCOPE_TOO_BROAD>',
            '<INTERVIEW_SCOPE_TOO_BROAD>épico com vários cards</INTERVIEW_SCOPE_TOO_BROAD>',
            '<INTERVIEW_SCOPE_TOO_BROAD/>',
            '<INTERVIEW_SCOPE_TOO_BROAD />',
            "<INTERVIEW_SCOPE_TOO_BROAD>\n</INTERVIEW_SCOPE_TOO_BROAD>",
        ];

        foreach ($variants as $tag) {
            $content = $prefix.' '.$tag;

            $this->assertTrue($parser->hasInterviewScopeTooBroad($content), $tag);
            $this->assertFalse($parser->looksInterviewReady($content), $tag);
            $this->assertNull($parser->extractInterviewSummary($content), $tag);
            $this->assertFalse($parser->hasInterviewComplete($content), $tag);
            $this->assertSame($prefix, $parser->extractTextOnly($content), $tag);
        }
    }

    public function test_scope_too_broad_tag_wins_over_interview_complete_and_heuristic(): void
    {
        $parser = new CardParserService;

        $withComplete = <<<'TXT'
A entrevista está fechada.

<INTERVIEW_COMPLETE>
<INTERVIEW_SUMMARY>
Problema: onboarding completo
</INTERVIEW_SUMMARY>
</INTERVIEW_COMPLETE>
<INTERVIEW_SCOPE_TOO_BROAD>épico</INTERVIEW_SCOPE_TOO_BROAD>
TXT;

        $this->assertTrue($parser->hasInterviewScopeTooBroad($withComplete));
        $this->assertFalse($parser->looksInterviewReady($withComplete));
        $this->assertNull($parser->extractInterviewSummary($withComplete));
        $this->assertFalse($parser->hasInterviewComplete($withComplete));
        $this->assertStringNotContainsString('INTERVIEW_SCOPE_TOO_BROAD', $parser->extractTextOnly($withComplete));
        $this->assertStringNotContainsString('INTERVIEW_COMPLETE', $parser->extractTextOnly($withComplete));
        $this->assertStringNotContainsString('épico', $parser->extractTextOnly($withComplete));

        $withHeuristic = 'A entrevista está fechada e você já pode gerar o card no produto. <INTERVIEW_SCOPE_TOO_BROAD/>';

        $this->assertFalse($parser->looksInterviewReady($withHeuristic));
        $this->assertNull($parser->extractInterviewSummary($withHeuristic));
        $this->assertFalse($parser->hasInterviewComplete($withHeuristic));
    }

    public function test_detects_generate_card_request(): void
    {
        $parser = new CardParserService;

        $this->assertTrue($parser->isGenerateCardRequest('gere o card'));
        $this->assertTrue($parser->isGenerateCardRequest('Gerar card'));
        $this->assertFalse($parser->isGenerateCardRequest('Pode gerar o card depois'));
        $this->assertFalse($parser->isGenerateCardRequest('Qual o problema?'));
    }

    public function test_normalizes_new_framework_card_json(): void
    {
        $parser = new CardParserService;
        $content = <<<'TXT'
Card gerado.
<CARD_JSON>
{
  "title": "Checkout MVP",
  "objetivo": "Permitir pagamento no checkout.",
  "como_funciona_hoje": "",
  "regras": ["Exibir meios de pagamento.", 12, ""],
  "onde": ["Checkout"],
  "aceite": ["Comprador com carrinho: ao pagar, confirma o pedido."],
  "o_que_nao_fazer": [],
  "stakeholders": ["Ana Silva"],
  "como_validar": "Em staging, abrir o checkout e pagar com cartão de teste.",
  "priority": "high"
}
</CARD_JSON>
TXT;

        $this->assertTrue($parser->hasCard($content));
        $this->assertSame([
            'title' => 'Checkout MVP',
            'objetivo' => 'Permitir pagamento no checkout.',
            'como_funciona_hoje' => null,
            'regras' => ['Exibir meios de pagamento.', '12'],
            'onde' => ['Checkout'],
            'aceite' => ['Comprador com carrinho: ao pagar, confirma o pedido.'],
            'o_que_nao_fazer' => [],
            'stakeholders' => ['Ana Silva'],
            'como_validar' => 'Em staging, abrir o checkout e pagar com cartão de teste.',
            'priority' => 'high',
        ], $parser->parse($content));
    }
}
