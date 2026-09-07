<?php

namespace Tests\Unit\Prompts;

use App\Prompts\SystemPromptCatalog;
use RuntimeException;
use Tests\TestCase;

class SystemPromptCatalogTest extends TestCase
{
    public function test_loads_the_configured_discovery_prompt(): void
    {
        $prompt = (new SystemPromptCatalog)->current('discovery');

        $this->assertSame('discovery', $prompt->name);
        $this->assertSame('v1', $prompt->version);
        $this->assertSame('discovery@v1', $prompt->identifier());
        $this->assertStringContainsString('NUNCA gere o card imediatamente.', $prompt->content);
        $this->assertStringContainsString('<CARD_JSON>', $prompt->content);
        $this->assertSame(hash('sha256', $prompt->content), $prompt->hash);
    }

    public function test_lists_available_versions(): void
    {
        $catalog = new SystemPromptCatalog;

        $this->assertContains('v1', $catalog->versions('discovery'));
        $this->assertContains('v1', $catalog->versions('interview'));
        $this->assertContains('v2', $catalog->versions('interview'));
        $this->assertContains('v3', $catalog->versions('interview'));
        $this->assertContains('v4', $catalog->versions('interview'));
        $this->assertContains('v1', $catalog->versions('card_generation'));
        $this->assertContains('v2', $catalog->versions('card_generation'));
    }

    public function test_loads_the_configured_interview_prompt(): void
    {
        $prompt = (new SystemPromptCatalog)->current('interview');

        $this->assertSame('interview', $prompt->name);
        $this->assertSame('v4', $prompt->version);
        $this->assertSame('interview@v4', $prompt->identifier());
        $this->assertStringContainsString('<INTERVIEW_COMPLETE>', $prompt->content);
        $this->assertStringContainsString('<INTERVIEW_SCOPE_TOO_BROAD></INTERVIEW_SCOPE_TOO_BROAD>', $prompt->content);
        $this->assertStringContainsString('fluxo completo de onboarding com KYC, abertura de conta e primeiro investimento', $prompt->content);
        $this->assertStringContainsString('Nunca emita `<INTERVIEW_COMPLETE>` nem `<INTERVIEW_SUMMARY>`', $prompt->content);
        $this->assertStringContainsString('Na mesma mensagem do resumo', $prompt->content);
        $this->assertStringContainsString('Objetivo: ...', $prompt->content);
        $this->assertStringNotContainsString('<CARD_JSON>', $prompt->content);
    }

    public function test_keeps_interview_v3_unchanged_and_loadable(): void
    {
        $prompt = (new SystemPromptCatalog)->get('interview', 'v3');

        $this->assertSame('v3', $prompt->version);
        $this->assertSame('interview@v3', $prompt->identifier());
        $this->assertStringContainsString('<INTERVIEW_COMPLETE>', $prompt->content);
        $this->assertStringNotContainsString('<INTERVIEW_SCOPE_TOO_BROAD>', $prompt->content);
        $this->assertStringNotContainsString('<CARD_JSON>', $prompt->content);
    }

    public function test_loads_the_configured_card_generation_prompt(): void
    {
        $prompt = (new SystemPromptCatalog)->current('card_generation');

        $this->assertSame('card_generation', $prompt->name);
        $this->assertSame('card_generation@v2', $prompt->identifier());
        $this->assertStringContainsString('<CARD_JSON>', $prompt->content);
        $this->assertStringContainsString('"objetivo"', $prompt->content);
    }

    public function test_throws_when_version_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt discovery@v99 não encontrado');

        (new SystemPromptCatalog)->get('discovery', 'v99');
    }

    public function test_rejects_invalid_identifiers(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identificador de prompt inválido');

        (new SystemPromptCatalog)->get('../secret', 'v1');
    }
}
