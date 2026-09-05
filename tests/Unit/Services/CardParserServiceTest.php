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

}
