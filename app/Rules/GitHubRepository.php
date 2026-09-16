<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class GitHubRepository implements ValidationRule
{
    /**
     * Aceita o identificador GitHub `owner/repo`.
     * URLs do github.com e clone SSH são normalizadas para esse formato
     * antes da validação. Recusa outros hosts, IP, esquema, `..`, espaços
     * e barras extras para reduzir risco de SSRF em chamadas futuras à API.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValid($value)) {
            $fail('O :attribute deve ser owner/repo ou uma URL do github.com.');
        }
    }

    /**
     * Extrai `owner/repo` de URL https/SSH do github.com, remove `.git` e espaços.
     * Valores que não são do GitHub permanecem como chegaram, para a validação recusar.
     */
    public static function normalize(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#\Agit@github\.com:([^/]+)/([^/]+)\z#i', $value, $matches) === 1) {
            return $matches[1].'/'.self::stripGitSuffix($matches[2]);
        }

        $url = $value;

        if (! str_contains($value, '://') && preg_match('#\A(?:www\.)?github\.com/.+#i', $value) === 1) {
            $url = 'https://'.$value;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (in_array($host, ['github.com', 'www.github.com'], true)) {
            $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));

            if (count($segments) >= 2 && $segments[0] !== '' && $segments[1] !== '') {
                return $segments[0].'/'.self::stripGitSuffix($segments[1]);
            }
        }

        return self::stripGitSuffix($value);
    }

    private function isValid(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        if ($value !== trim($value)) {
            return false;
        }

        if (str_contains($value, '..') || preg_match('/\s/', $value) === 1) {
            return false;
        }

        if (str_contains($value, '://') || str_contains($value, ':') || str_contains($value, '\\') || str_contains($value, '@')) {
            return false;
        }

        if (preg_match('/\A([A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?)\/([A-Za-z0-9](?:[A-Za-z0-9._-]{0,98}[A-Za-z0-9])?)\z/', $value, $matches) !== 1) {
            return false;
        }

        $owner = $matches[1];

        if (strcasecmp($owner, 'localhost') === 0) {
            return false;
        }

        return filter_var($owner, FILTER_VALIDATE_IP) === false;
    }

    private static function stripGitSuffix(string $repository): string
    {
        return (string) preg_replace('/\.git$/i', '', $repository);
    }
}
