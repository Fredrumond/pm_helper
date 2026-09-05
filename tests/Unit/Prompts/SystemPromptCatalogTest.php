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
        $this->assertContains('v1', (new SystemPromptCatalog)->versions('discovery'));
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
