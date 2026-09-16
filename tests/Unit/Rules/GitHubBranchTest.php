<?php

namespace Tests\Unit\Rules;

use App\Rules\GitHubBranch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GitHubBranchTest extends TestCase
{
    #[DataProvider('validBranches')]
    public function test_accepts_github_branch_names(string $branch): void
    {
        $this->assertTrue($this->passes($branch), $branch);
    }

    #[DataProvider('invalidBranches')]
    public function test_rejects_refs_spaces_and_unsafe_names(mixed $branch): void
    {
        $this->assertFalse($this->passes($branch), is_string($branch) ? $branch : gettype($branch));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validBranches(): array
    {
        return [
            'main' => ['main'],
            'master' => ['master'],
            'develop' => ['develop'],
            'feature slash' => ['feature/docs'],
            'hyphen and dot' => ['release-1.0'],
            'underscore' => ['hotfix_login'],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidBranches(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['feature docs'],
            'leading space' => [' main'],
            'dot-dot' => ['foo/../etc'],
            'double slash' => ['feature//docs'],
            'leading slash' => ['/main'],
            'trailing slash' => ['main/'],
            'lock suffix' => ['main.lock'],
            'refs heads' => ['refs/heads/main'],
            'wildcard' => ['feat*'],
            'null' => [null],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizedBranches(): array
    {
        return [
            'trim' => ['  develop  ', 'develop'],
            'strip heads prefix' => ['refs/heads/main', 'main'],
            'already short' => ['feature/docs', 'feature/docs'],
        ];
    }

    #[DataProvider('normalizedBranches')]
    public function test_normalizes_heads_prefix_and_whitespace(string $input, string $expected): void
    {
        $this->assertSame($expected, GitHubBranch::normalize($input));
    }

    private function passes(mixed $value): bool
    {
        $failed = false;

        (new GitHubBranch)->validate('branch', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
