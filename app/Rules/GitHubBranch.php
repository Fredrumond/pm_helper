<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class GitHubBranch implements ValidationRule
{
    /**
     * Aceita o nome curto da branch (`main`, `feature/docs`).
     * Recusa refs completas, espaços, `..`, barras extras e caracteres
     * que o git rejeita, para o `ref` ir ao MCP sem injeção de caminho.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValid($value)) {
            $fail('A :attribute deve ser o nome da branch no GitHub, por exemplo main ou feature/docs.');
        }
    }

    /**
     * Remove espaços e o prefixo `refs/heads/` se alguém colar a ref completa.
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

        return (string) preg_replace('#\Arefs/heads/#i', '', $value);
    }

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)) {
            return false;
        }

        if (strlen($value) > 255) {
            return false;
        }

        if (
            str_contains($value, '..')
            || str_contains($value, '//')
            || str_contains($value, '@{')
            || str_contains($value, '\\')
            || str_contains($value, ':')
        ) {
            return false;
        }

        if (str_starts_with($value, '/') || str_ends_with($value, '/') || str_ends_with($value, '.lock')) {
            return false;
        }

        if (str_starts_with($value, '-') || str_starts_with($value, '.') || $value === '@') {
            return false;
        }

        if (preg_match('/[\s~^:?*\[\]]/', $value) === 1) {
            return false;
        }

        if (preg_match('#\Arefs/#i', $value) === 1) {
            return false;
        }

        if (preg_match('/\A[A-Za-z0-9._][A-Za-z0-9._\-\/]*\z/', $value) !== 1) {
            return false;
        }

        foreach (explode('/', $value) as $part) {
            if ($part === '' || str_starts_with($part, '.') || str_ends_with($part, '.lock')) {
                return false;
            }
        }

        return true;
    }
}
