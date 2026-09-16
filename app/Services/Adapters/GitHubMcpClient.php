<?php

namespace App\Services\Adapters;

use App\Exceptions\GitHubIntegrationException;
use App\Services\ProjectDocsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use stdClass;

class GitHubMcpClient
{
    private const PROTOCOL_VERSION = '2025-03-26';

    private ?string $token = null;

    private ?string $sessionId = null;

    private int $nextId = 1;

    public function initialize(string $installationToken): void
    {
        $this->reset();
        $this->token = $installationToken;

        $response = $this->postRpc('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'pm-helper',
                'version' => $this->clientVersion(),
            ],
        ]);

        $this->sessionId = $this->header($response, 'Mcp-Session-Id');
        $this->assertRpcSuccess($this->decodeRpc($response, $this->nextId - 1));

        $this->postNotification('notifications/initialized');
    }

    public function getFileContents(string $owner, string $repo, string $path, ?string $ref = null): GitHubMcpFileResult
    {
        $arguments = [
            'owner' => $owner,
            'repo' => $repo,
            'path' => $path,
            'fields' => ['type', 'name', 'path'],
        ];

        if ($ref !== null && $ref !== '') {
            $arguments['ref'] = $ref;
        }

        $response = $this->postRpc('tools/call', [
            'name' => 'get_file_contents',
            'arguments' => $arguments,
        ]);

        $rpc = $this->decodeRpc($response, $this->nextId - 1);

        return $this->interpretToolResult($rpc);
    }

    public function close(): void
    {
        if ($this->token !== null && $this->sessionId !== null) {
            try {
                Http::withToken($this->token)
                    ->timeout($this->timeout())
                    ->withHeaders($this->headers())
                    ->delete($this->url());
            } catch (ConnectionException) {
                // Encerrar a sessão é best-effort; o token expira sozinho.
            }
        }

        $this->reset();
    }

    private function reset(): void
    {
        $this->token = null;
        $this->sessionId = null;
        $this->nextId = 1;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function postRpc(string $method, array $params): Response
    {
        $id = $this->nextId++;

        return $this->send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function postNotification(string $method, array $params = []): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if ($params !== []) {
            $payload['params'] = $params;
        }

        $this->send($payload, expectBody: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload, bool $expectBody = true): Response
    {
        if ($this->token === null) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP client has no installation token.',
            );
        }

        try {
            $pending = Http::withToken($this->token)
                ->timeout($this->timeout())
                ->connectTimeout(min(10, $this->timeout()))
                ->withHeaders($this->headers())
                ->withBody(
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    'application/json',
                );

            $response = $pending->post($this->url());
        } catch (ConnectionException $exception) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_TIMEOUT,
                'MCP request timed out.',
                $exception,
            );
        } catch (JsonException $exception) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP request could not be encoded.',
                $exception,
            );
        }

        if (! $expectBody && in_array($response->status(), [200, 202, 204], true)) {
            return $response;
        }

        $this->throwIfHttpFailed($response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
            'X-MCP-Toolsets' => 'repos',
            'X-MCP-Readonly' => 'true',
            'User-Agent' => 'pm-helper',
        ];

        if ($this->sessionId !== null && $this->sessionId !== '') {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }

    private function throwIfHttpFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 429 || $this->looksLikeRateLimit($response)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_RATE_LIMIT,
                'MCP rate limited the request.',
            );
        }

        if (in_array($status, [401, 403], true)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'MCP rejected the installation token.',
            );
        }

        throw new GitHubIntegrationException(
            ProjectDocsResult::ERROR_MCP,
            'MCP HTTP request failed.',
        );
    }

    private function looksLikeRateLimit(Response $response): bool
    {
        return (bool) preg_match('/rate.?limit|too many requests/i', $response->body());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRpc(Response $response, int $id): array
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $payload = str_contains($contentType, 'text/event-stream')
            ? $this->parseSse($response->body(), $id)
            : $response->json();

        if (! is_array($payload)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP returned a non-JSON response.',
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSse(string $body, int $id): array
    {
        $matched = null;

        foreach (preg_split("/\r\n\r\n|\n\n/", $body) ?: [] as $block) {
            $dataLines = [];

            foreach (preg_split("/\r\n|\n/", $block) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }

            if ($dataLines === []) {
                continue;
            }

            $decoded = json_decode(implode("\n", $dataLines), true);

            if (! is_array($decoded)) {
                continue;
            }

            if (($decoded['id'] ?? null) === $id || ($decoded['id'] ?? null) === (string) $id) {
                $matched = $decoded;
            }
        }

        if ($matched === null) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP SSE stream did not include a JSON-RPC response.',
            );
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $rpc
     */
    private function assertRpcSuccess(array $rpc): void
    {
        if (isset($rpc['error'])) {
            $this->throwRpcError($rpc['error']);
        }
    }

    /**
     * @param  array<string, mixed>  $rpc
     */
    private function interpretToolResult(array $rpc): GitHubMcpFileResult
    {
        if (isset($rpc['error'])) {
            $message = $this->rpcErrorMessage($rpc['error']);

            if ($this->isMissingPath($message)) {
                return GitHubMcpFileResult::missing();
            }

            $this->throwRpcError($rpc['error']);
        }

        $result = $rpc['result'] ?? null;

        if (! is_array($result)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP tool result was missing.',
            );
        }

        $message = $this->extractText($result);

        if (($result['isError'] ?? false) === true) {
            if ($this->isMissingPath($message)) {
                return GitHubMcpFileResult::missing();
            }

            $this->throwToolError($message);
        }

        if ($this->hasResourceLink($result)) {
            return GitHubMcpFileResult::tooLarge();
        }

        $resource = $this->extractResource($result);

        if ($resource !== null) {
            $text = (string) ($resource['text'] ?? '');
            $hasBlob = array_key_exists('blob', $resource)
                && $resource['blob'] !== null
                && $resource['blob'] !== '';

            if ($hasBlob && $text === '') {
                return GitHubMcpFileResult::binary();
            }

            $mimeType = isset($resource['mimeType']) ? (string) $resource['mimeType'] : null;

            if ($mimeType !== null && $mimeType !== '' && ! $this->isTextMime($mimeType)) {
                return GitHubMcpFileResult::binary();
            }

            return GitHubMcpFileResult::file($text, $mimeType);
        }

        $entries = $this->directoryEntries($message);

        if ($entries !== null) {
            return GitHubMcpFileResult::directory($entries);
        }

        if ($message !== '') {
            return GitHubMcpFileResult::file($message, 'text/plain');
        }

        return GitHubMcpFileResult::missing();
    }

    private function throwRpcError(mixed $error): never
    {
        $message = $this->rpcErrorMessage($error);
        $this->throwToolError($message);
    }

    private function throwToolError(string $message): never
    {
        if ($this->looksLikeAuth($message)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_AUTH,
                'MCP authentication failed.',
            );
        }

        if ((bool) preg_match('/rate.?limit|too many requests/i', $message)) {
            throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_RATE_LIMIT,
                'MCP rate limited the request.',
            );
        }

        throw new GitHubIntegrationException(
            ProjectDocsResult::ERROR_MCP,
            'MCP tool call failed.',
        );
    }

    private function rpcErrorMessage(mixed $error): string
    {
        if (is_array($error)) {
            return (string) ($error['message'] ?? '');
        }

        return is_string($error) ? $error : '';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractText(array $result): string
    {
        $parts = [];

        foreach ($result['content'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'text' && isset($item['text'])) {
                $parts[] = (string) $item['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    private function extractResource(array $result): ?array
    {
        foreach ($result['content'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'resource' && isset($item['resource']) && is_array($item['resource'])) {
                return $item['resource'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function hasResourceLink(array $result): bool
    {
        foreach ($result['content'] ?? [] as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'resource_link') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{type: string, name: string, path: string}>|null
     */
    private function directoryEntries(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return null;
        }

        if ($decoded === []) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            $type = strtolower((string) ($entry['type'] ?? ''));
            $name = (string) ($entry['name'] ?? '');
            $path = ltrim((string) ($entry['path'] ?? $name), '/');

            if (
                ! in_array($type, ['file', 'dir', 'directory', 'symlink', 'submodule'], true)
                || ($name === '' && $path === '')
            ) {
                return null;
            }

            $entries[] = [
                'type' => $type,
                'name' => $name !== '' ? $name : basename($path),
                'path' => $path,
            ];
        }

        return $entries;
    }

    private function isTextMime(string $mimeType): bool
    {
        $mimeType = strtolower(trim(explode(';', $mimeType)[0]));

        return str_starts_with($mimeType, 'text/')
            || $mimeType === 'application/json'
            || $mimeType === 'application/xml'
            || $mimeType === 'application/yaml'
            || $mimeType === 'application/x-yaml'
            || str_ends_with($mimeType, '+json')
            || str_ends_with($mimeType, '+xml')
            || str_ends_with($mimeType, '+yaml');
    }

    private function isMissingPath(string $message): bool
    {
        return (bool) preg_match(
            '/not found|404|does not exist|no file or directory|path did not point|no such file/i',
            $message,
        );
    }

    private function looksLikeAuth(string $message): bool
    {
        return (bool) preg_match(
            '/unauthorized|bad credentials|requires authentication|not accessible|badly formatted|forbidden/i',
            $message,
        );
    }

    private function header(Response $response, string $name): ?string
    {
        $value = $response->header($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function clientVersion(): string
    {
        $releases = config('versoes.releases', []);

        if (is_array($releases) && isset($releases[0]['versao'])) {
            return (string) $releases[0]['versao'];
        }

        return '0.0.0';
    }

    private function url(): string
    {
        $url = trim((string) config('mcp.github.url', 'https://api.githubcopilot.com/mcp/'));

        return $url !== '' ? $url : 'https://api.githubcopilot.com/mcp/';
    }

    private function timeout(): int
    {
        $timeout = (int) config('mcp.github.timeout', 20);

        return $timeout > 0 ? $timeout : 20;
    }
}
