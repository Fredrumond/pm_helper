<?php

namespace App\Support;

class DiscoveryQuestions
{
    /**
     * @return list<string>
     */
    public static function fromMarkdown(string $markdown): array
    {
        $section = self::section($markdown);
        if ($section === null) {
            return [];
        }

        $questions = [];
        foreach (self::items($section) as $item) {
            $question = self::question($item);
            if ($question === '' || self::isClosedNotice($question)) {
                continue;
            }

            $questions[] = $question;
        }

        return $questions;
    }

    private static function section(string $markdown): ?string
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        if (preg_match('/^## 6\. Dúvidas[ \t]*\n?(.*?)(?=^## |\z)/ms', $markdown, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private static function items(string $section): array
    {
        if (preg_match_all('/^[ \t]*-[ \t]+(.+)$/m', $section, $matches) < 1) {
            return [];
        }

        return array_values($matches[1]);
    }

    private static function question(string $item): string
    {
        $parts = explode('—', $item, 2);

        return trim($parts[0]);
    }

    private static function isClosedNotice(string $text): bool
    {
        $normalized = mb_strtolower(rtrim(trim($text), '.'));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized === 'nenhuma dúvida em aberto';
    }
}
