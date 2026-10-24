<?php

namespace App\Services;

class MarkdownFrontmatterParser
{
    /**
     * @return array{status: ?string, body: string}
     */
    public function parse(string $markdown): array
    {
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/s', $markdown, $matches)) {
            return [
                'status' => null,
                'body' => $markdown,
            ];
        }

        $status = $this->parseStatus($matches[1]);

        return [
            'status' => $status,
            'body' => ltrim($matches[2], "\r\n"),
        ];
    }

    private function parseStatus(string $frontmatterBlock): ?string
    {
        foreach (preg_split('/\r?\n/', $frontmatterBlock) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^status\s*:\s*(.+)$/iu', $line, $matches) !== 1) {
                continue;
            }

            $value = trim($matches[1]);
            $value = trim($value, " \t\"'");

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
