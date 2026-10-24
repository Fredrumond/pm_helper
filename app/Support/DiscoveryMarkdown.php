<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class DiscoveryMarkdown
{
    public static function toHtml(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addRenderer(FencedCode::class, new MermaidFencedCodeRenderer, 10);

        return trim((string) (new MarkdownConverter($environment))->convert($markdown));
    }

    public static function title(string $markdown): string
    {
        $title = 'Discovery';
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) === 1) {
            $candidate = trim(preg_replace('/\s+/', ' ', strip_tags($matches[1])) ?? '');
            if ($candidate !== '') {
                $title = $candidate;
            }
        }

        return mb_substr($title, 0, 120);
    }
}
