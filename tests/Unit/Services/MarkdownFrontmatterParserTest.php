<?php

namespace Tests\Unit\Services;

use App\Services\MarkdownFrontmatterParser;
use PHPUnit\Framework\TestCase;

class MarkdownFrontmatterParserTest extends TestCase
{
    public function test_returns_original_content_when_there_is_no_frontmatter(): void
    {
        $markdown = "# Título\n\nParágrafo.";

        $parsed = (new MarkdownFrontmatterParser)->parse($markdown);

        $this->assertNull($parsed['status']);
        $this->assertSame($markdown, $parsed['body']);
    }

    public function test_extracts_status_and_body_from_frontmatter(): void
    {
        $markdown = <<<'MD'
---
id: exemplo
status: deprecated
---

# Corpo
MD;

        $parsed = (new MarkdownFrontmatterParser)->parse($markdown);

        $this->assertSame('deprecated', $parsed['status']);
        $this->assertSame('# Corpo', $parsed['body']);
    }

    public function test_frontmatter_without_status_field_yields_null_status(): void
    {
        $markdown = <<<'MD'
---
id: exemplo
entity: Anotações
---

# Corpo
MD;

        $parsed = (new MarkdownFrontmatterParser)->parse($markdown);

        $this->assertNull($parsed['status']);
        $this->assertStringStartsWith('# Corpo', $parsed['body']);
    }
}
