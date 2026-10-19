<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;

class DocumentChunker
{
    public const SIZE = RagConfig::DEFAULT_CHUNK_SIZE;

    private const SHORT_SECTION_RATIO = 0.2;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly ?RagConfig $rag = null,
    ) {}

    public function chunk(Document $document): void
    {
        $document->chunks()->delete();

        $size = $this->rag()->chunkSize();
        $pieces = $this->pieces($document->content, $this->rag()->strategy(), $size);
        $title = $this->documentTitle($document);

        foreach ($pieces as $index => $piece) {
            $content = $this->withOrigin($title, $document->name, $piece['heading_path'], $piece['text']);

            DocumentChunk::query()->create([
                'document_id' => $document->id,
                'content' => $content,
                'chunk_index' => $index,
                'heading_path' => $piece['heading_path'],
                'embedding' => $this->embeddings->embed($content),
            ]);
        }
    }

    /**
     * @return list<array{text: string, heading_path: ?string}>
     */
    private function pieces(string $content, string $strategy, int $size): array
    {
        if ($size <= 0) {
            return [['text' => $content, 'heading_path' => null]];
        }

        return match ($strategy) {
            RagConfig::STRATEGY_PARAGRAPH => $this->paragraphPieces($content, $size),
            RagConfig::STRATEGY_MARKDOWN => $this->markdownPieces($content, $size),
            default => $this->sizePieces($content, $size),
        };
    }

    /**
     * @return list<array{text: string, heading_path: ?string}>
     */
    private function sizePieces(string $content, int $size): array
    {
        $parts = mb_str_split($content, $size);

        if ($parts === []) {
            $parts = [$content];
        }

        return array_map(
            fn (string $text): array => ['text' => $text, 'heading_path' => null],
            $parts,
        );
    }

    /**
     * @return list<array{text: string, heading_path: ?string}>
     */
    private function paragraphPieces(string $content, int $size): array
    {
        return array_map(
            fn (string $text): array => ['text' => $text, 'heading_path' => null],
            $this->paragraphTexts($content, $size),
        );
    }

    /**
     * @return list<string>
     */
    private function paragraphTexts(string $content, int $size): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $parts = preg_split("/\n[ \t]*\n/", $normalized) ?: [];
        $paragraphs = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            foreach ($this->fittingPieces($part, $size) as $piece) {
                $paragraphs[] = $piece;
            }
        }

        if ($paragraphs === []) {
            return [$normalized];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if ($current !== '' && mb_strlen($candidate) > $size) {
                $chunks[] = $current;
                $current = $paragraph;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function fittingPieces(string $content, int $size): array
    {
        if (mb_strlen($content) <= $size) {
            return [$content];
        }

        $parts = mb_str_split($content, $size);

        return $parts === [] ? [$content] : $parts;
    }

    /**
     * @return list<array{text: string, heading_path: ?string}>
     */
    private function markdownPieces(string $content, int $size): array
    {
        $sections = $this->markdownSections($content);
        $hasHeading = false;

        foreach ($sections as $section) {
            if ($section['path'] !== null) {
                $hasHeading = true;
                break;
            }
        }

        if (! $hasHeading) {
            return $this->paragraphPieces($content, $size);
        }

        $pieces = [];

        foreach ($this->mergeShortSections($sections, $size) as $section) {
            $text = $this->sectionText($section);

            if (mb_strlen($text) <= $size) {
                $pieces[] = ['text' => $text, 'heading_path' => $section['path']];

                continue;
            }

            foreach ($this->paragraphTexts($text, $size) as $part) {
                $pieces[] = ['text' => $part, 'heading_path' => $section['path']];
            }
        }

        return $pieces === [] ? [['text' => $content, 'heading_path' => null]] : $pieces;
    }

    /**
     * @return list<array{lines: list<string>, path: ?string}>
     */
    private function markdownSections(string $content): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = $normalized === '' ? [] : explode("\n", $normalized);
        $sections = [];
        $current = null;
        $stack = [];

        $push = function () use (&$sections, &$current): void {
            if ($current === null) {
                return;
            }

            $sections[] = $current;
            $current = null;
        };

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,3})\s+(.+?)\s*$/', $line, $matches) !== 1) {
                if ($current === null) {
                    $current = ['lines' => [$line], 'path' => null];
                } else {
                    $current['lines'][] = $line;
                }

                continue;
            }

            $push();

            $level = strlen($matches[1]);
            $stack = array_filter(
                $stack,
                fn (int $existing): bool => $existing < $level,
                ARRAY_FILTER_USE_KEY,
            );
            $stack[$level] = trim($matches[2]);
            ksort($stack);

            $current = [
                'lines' => [$line],
                'path' => $this->headingPath($stack, $level),
            ];
        }

        $push();

        return $sections;
    }

    /**
     * @param  array<int, string>  $stack
     */
    private function headingPath(array $stack, int $level): string
    {
        $parts = [];

        foreach ($stack as $existingLevel => $text) {
            if ($existingLevel === 1 && $level !== 1) {
                continue;
            }

            $parts[] = $text;
        }

        return implode(' › ', $parts);
    }

    /**
     * @param  list<array{lines: list<string>, path: ?string}>  $sections
     * @return list<array{lines: list<string>, path: ?string}>
     */
    private function mergeShortSections(array $sections, int $size): array
    {
        $count = count($sections);
        $merged = [];
        $index = 0;

        while ($index < $count) {
            $current = $sections[$index];

            if ($this->isShort($current, $size) && $index + 1 < $count) {
                $joined = $this->tryJoin($current, $sections[$index + 1], $size);

                if ($joined !== null) {
                    $sections[$index + 1] = $joined;
                    $index++;

                    continue;
                }
            }

            if ($this->isShort($current, $size) && $index === $count - 1 && $merged !== []) {
                $lastIndex = array_key_last($merged);
                $joined = $this->tryJoin($merged[$lastIndex], $current, $size);

                if ($joined !== null) {
                    $merged[$lastIndex] = $joined;
                    break;
                }
            }

            $merged[] = $current;
            $index++;
        }

        return $merged;
    }

    /**
     * @param  array{lines: list<string>, path: ?string}  $section
     */
    private function isShort(array $section, int $size): bool
    {
        return mb_strlen($this->sectionText($section)) < ($size * self::SHORT_SECTION_RATIO);
    }

    /**
     * @param  array{lines: list<string>, path: ?string}  $left
     * @param  array{lines: list<string>, path: ?string}  $right
     * @return array{lines: list<string>, path: ?string}|null
     */
    private function tryJoin(array $left, array $right, int $size): ?array
    {
        $text = $this->sectionText($left)."\n\n".$this->sectionText($right);

        if (mb_strlen($text) > $size) {
            return null;
        }

        return [
            'lines' => [$text],
            'path' => $this->joinedPath($left['path'], $right['path']),
        ];
    }

    private function joinedPath(?string $left, ?string $right): ?string
    {
        if ($left === null || $left === '') {
            return ($right === null || $right === '') ? null : $right;
        }

        if ($right === null || $right === '') {
            return $left;
        }

        if ($left === $right) {
            return $left;
        }

        return $left.' · '.$right;
    }

    /**
     * @param  array{lines: list<string>, path: ?string}  $section
     */
    private function sectionText(array $section): string
    {
        return rtrim(implode("\n", $section['lines']));
    }

    private function documentTitle(Document $document): string
    {
        if (preg_match('/^#\s+(.+)$/m', $document->content, $matches) === 1) {
            return trim($matches[1]);
        }

        return $document->name;
    }

    private function withOrigin(string $title, string $filename, ?string $headingPath, string $text): string
    {
        $line = $title.' ('.$filename.')';

        if ($headingPath !== null && $headingPath !== '') {
            $line .= ' › '.$headingPath;
        }

        return $line."\n".$text;
    }

    private function rag(): RagConfig
    {
        return $this->rag ?? app(RagConfig::class);
    }
}
