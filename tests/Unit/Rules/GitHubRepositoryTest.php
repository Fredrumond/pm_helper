<?php

namespace Tests\Unit\Rules;

use App\Rules\GitHubRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GitHubRepositoryTest extends TestCase
{
    #[DataProvider('validRepositories')]
    public function test_accepts_owner_repo_format(string $repository): void
    {
        $this->assertTrue($this->passes($repository), $repository);
    }

    #[DataProvider('invalidRepositories')]
    public function test_rejects_url_ip_scheme_host_and_malformed_values(mixed $repository): void
    {
        $this->assertFalse($this->passes($repository), is_string($repository) ? $repository : gettype($repository));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validRepositories(): array
    {
        return [
            'owner/repo' => ['octocat/hello-world'],
            'org with hyphen' => ['my-org/mobile-app'],
            'repo with underscore and dot' => ['octocat/hello_world.name'],
            'short names' => ['a/b'],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidRepositories(): array
    {
        return [
            'https url without normalize' => ['https://github.com/octocat/hello-world'],
            'http url without normalize' => ['http://github.com/octocat/hello-world'],
            'ip' => ['127.0.0.1/repo'],
            'extra slash' => ['octocat/hello/world'],
            'missing repo' => ['octocat'],
            'empty' => [''],
            'spaces' => ['octocat/hello world'],
            'leading space' => [' octocat/hello-world'],
            'dot-dot' => ['octocat/../etc'],
            'trailing slash' => ['octocat/hello-world/'],
            'host path' => ['github.com/octocat'],
            'localhost' => ['localhost/repo'],
            'null' => [null],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizedRepositories(): array
    {
        return [
            'already owner/repo' => ['octocat/hello-world', 'octocat/hello-world'],
            'https url' => ['https://github.com/octocat/hello-world', 'octocat/hello-world'],
            'https url with git suffix' => ['https://github.com/octocat/hello-world.git', 'octocat/hello-world'],
            'https url with extra path' => ['https://github.com/octocat/hello-world/issues/1', 'octocat/hello-world'],
            'www host' => ['https://www.github.com/octocat/hello-world', 'octocat/hello-world'],
            'host without scheme' => ['github.com/octocat/hello-world', 'octocat/hello-world'],
            'ssh clone' => ['git@github.com:octocat/hello-world.git', 'octocat/hello-world'],
            'trailing spaces' => ['  octocat/hello-world  ', 'octocat/hello-world'],
            'dot git suffix only' => ['octocat/hello-world.git', 'octocat/hello-world'],
            'gitlab stays invalid' => ['https://gitlab.com/org/repo', 'https://gitlab.com/org/repo'],
        ];
    }

    #[DataProvider('normalizedRepositories')]
    public function test_normalizes_github_urls_to_owner_repo(string $input, string $expected): void
    {
        $this->assertSame($expected, GitHubRepository::normalize($input));
    }

    private function passes(mixed $value): bool
    {
        $failed = false;

        (new GitHubRepository)->validate('repository', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
