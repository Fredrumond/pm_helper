<?php

namespace Tests\Unit\Support;

use App\Support\DiscoveryMarkdown;
use Tests\TestCase;

class DiscoveryMarkdownTest extends TestCase
{
    public function test_renders_markdown_and_strips_raw_html_and_unsafe_links(): void
    {
        $html = DiscoveryMarkdown::toHtml(<<<'MD'
# Titulo

Paragrafo.

<script>alert(1)</script>

<img src=x onerror=alert(2)>

[clique](javascript:alert(1))
MD);

        $this->assertStringContainsString('<h1>Titulo</h1>', $html);
        $this->assertStringContainsString('<p>Paragrafo.</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_mermaid_fence_becomes_a_diagram_and_escapes_html(): void
    {
        $html = DiscoveryMarkdown::toHtml(<<<'MD'
## Fluxo

```mermaid
flowchart LR
    A["<img src=x onerror=alert(1)>"] --> B[Solicitado]
```

```php
echo 1;
```
MD);

        $this->assertStringContainsString('<pre class="mermaid">flowchart LR', $html);
        $this->assertStringContainsString('--&gt; B[Solicitado]', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('language-mermaid', $html);
        $this->assertStringContainsString('language-php', $html);
    }

    public function test_flowchart_fence_keeps_the_direction_in_the_diagram_source(): void
    {
        $html = DiscoveryMarkdown::toHtml(<<<'MD'
```flowchart LR
A[Card] --> B
```

```
flowchart TD
  A --> B
```
MD);

        $this->assertSame(2, substr_count($html, '<pre class="mermaid">'));
        $this->assertStringContainsString("flowchart LR\nA[Card] --&gt; B", $html);
        $this->assertStringContainsString("flowchart TD\n  A --&gt; B", $html);
    }

    public function test_title_comes_from_the_first_heading(): void
    {
        $this->assertSame('Aviso de duplicata', DiscoveryMarkdown::title("# Aviso de duplicata\n\nCorpo"));
        $this->assertSame('Discovery', DiscoveryMarkdown::title('sem titulo'));
        $this->assertSame('Titulo', DiscoveryMarkdown::title("# <script>Titulo</script>\n"));
    }
}
