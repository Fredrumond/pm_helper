<?php

namespace App\Support;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

class MermaidFencedCodeRenderer implements NodeRendererInterface
{
    /** @var list<string> */
    private const DIAGRAMS = [
        'flowchart',
        'graph',
        'sequencediagram',
        'classdiagram',
        'statediagram',
        'statediagram-v2',
        'erdiagram',
        'journey',
        'gantt',
        'pie',
        'mindmap',
        'timeline',
        'gitgraph',
        'quadrantchart',
        'requirementdiagram',
        'c4context',
        'c4container',
        'c4component',
        'c4dynamic',
        'c4deployment',
        'block-beta',
        'sankey-beta',
        'xychart-beta',
        'packet-beta',
        'architecture-beta',
        'kanban',
        'radar-beta',
    ];

    public function __construct(private readonly FencedCodeRenderer $code = new FencedCodeRenderer) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        FencedCode::assertInstanceOf($node);

        $source = $this->diagramSource($node);
        if ($source === null) {
            return $this->code->render($node, $childRenderer);
        }

        return new HtmlElement('pre', ['class' => 'mermaid'], Xml::escape($source));
    }

    private function diagramSource(FencedCode $node): ?string
    {
        $words = array_values(array_filter(
            $node->getInfoWords(),
            static fn (string $word): bool => $word !== '',
        ));
        $literal = rtrim($node->getLiteral(), "\r\n");
        $first = strtolower($words[0] ?? '');

        if ($first === 'mermaid') {
            return $literal !== '' ? $literal : null;
        }

        if ($this->isDiagram($first)) {
            $header = implode(' ', $words);

            return $literal === '' ? $header : $header."\n".$literal;
        }

        if ($words === [] && $this->literalIsDiagram($literal)) {
            return $literal;
        }

        return null;
    }

    private function literalIsDiagram(string $literal): bool
    {
        if (preg_match('/^\s*([A-Za-z][\w-]*)/', $literal, $matches) !== 1) {
            return false;
        }

        return $this->isDiagram(strtolower($matches[1]));
    }

    private function isDiagram(string $keyword): bool
    {
        return in_array($keyword, self::DIAGRAMS, true);
    }
}
