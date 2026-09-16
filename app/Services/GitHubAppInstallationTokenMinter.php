<?php

namespace App\Services;

use App\Exceptions\GitHubIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubAppInstallationTokenMinter
{
    private const TOKEN_URL = 'https://api.github.com/app/installations/%s/access_tokens';

    private const API_VERSION = '2022-11-28';

    public function hasCredentials(): bool
    {
        return $this->appId() !== ''
            && $this->privateKey() !== ''
            && $this->installationId() !== '';
    }

    /**
     * Mint um installation token efêmero. O valor só existe no retorno;
     * não é gravado em cache, sessão, banco ou log.
     */
    public function mint(): string
    {
        if (! $this->hasCredentials()) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MISSING_CREDENTIALS,
                'GitHub App credentials are missing.',
            );
        }

        $jwt = $this->makeJwt();
        $url = sprintf(self::TOKEN_URL, rawurlencode($this->installationId()));

        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout(min(10, $this->timeout()))
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer '.$jwt,
                    'X-GitHub-Api-Version' => self::API_VERSION,
                    'User-Agent' => 'pm-helper',
                ])
                ->asJson()
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_TIMEOUT,
                'GitHub App token mint timed out.',
                $exception,
            );
        } finally {
            unset($jwt);
        }

        $this->throwIfMintFailed($response);

        $token = trim((string) $response->json('token'));

        if ($token === '') {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'GitHub App token mint returned an empty token.',
            );
        }

        return $token;
    }

    private function throwIfMintFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 429 || $this->looksLikeRateLimit($response)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_RATE_LIMIT,
                'GitHub API rate limited while minting the installation token.',
            );
        }

        if (in_array($status, [401, 403], true)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'GitHub App authentication failed while minting the installation token.',
            );
        }

        if ($status >= 500) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'GitHub API failed while minting the installation token.',
            );
        }

        throw new GitHubIntegrationException(
            ProjectDocsResult::ERROR_AUTH,
            'GitHub App token mint failed.',
        );
    }

    private function looksLikeRateLimit(Response $response): bool
    {
        $remaining = $response->header('X-RateLimit-Remaining');

        if ($remaining !== null && $remaining !== '' && (int) $remaining === 0) {
            return true;
        }

        return (bool) preg_match('/rate.?limit|too many requests/i', $response->body());
    }

    private function makeJwt(): string
    {
        $now = time();
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode((string) json_encode([
            'iat' => $now - 60,
            'exp' => $now + 600,
            'iss' => $this->appId(),
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$payload;
        $key = openssl_pkey_get_private($this->privateKey());

        if ($key === false) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'GitHub App private key is invalid.',
            );
        }

        $signature = '';

        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'GitHub App JWT signing failed.',
            );
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function appId(): string
    {
        return trim((string) config('services.github_app.app_id'));
    }

    private function installationId(): string
    {
        return trim((string) config('services.github_app.installation_id'));
    }

    private function privateKey(): string
    {
        $key = trim((string) config('services.github_app.private_key'));

        if ($key === '') {
            return '';
        }

        $key = str_replace(["\r\n", "\r"], "\n", $key);
        $key = str_replace('\\n', "\n", $key);

        return trim($key);
    }

    private function timeout(): int
    {
        $timeout = (int) config('mcp.github.timeout', 20);

        return $timeout > 0 ? $timeout : 20;
    }
}
