<?php

namespace App\Services\Adapters;

use App\Contracts\AgentGateway;
use App\Services\Agents\AgentProviderException;
use App\Services\Agents\AgentTransientException;
use App\Services\Agents\DiscoveryAgentHandle;
use App\Services\Agents\DiscoveryAgentRequest;
use App\Services\Agents\DiscoveryAgentSnapshot;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CursorAgentAdapter implements AgentGateway
{
    public function provider(): string
    {
        return 'cursor';
    }

    public function start(DiscoveryAgentRequest $request): DiscoveryAgentHandle
    {
        $body = [
            'name' => mb_substr($request->name, 0, 80),
            'prompt' => ['text' => $request->prompt],
            'repos' => [[
                'url' => $this->repositoryUrl($request->repository),
                'startingRef' => $request->branch,
            ]],
            'autoCreatePR' => false,
        ];

        $model = trim((string) config('services.cursor.model'));
        if ($model !== '') {
            $body['model'] = ['id' => $model];
        }

        $created = $this->send('POST', '/agents', $body);
        $agentId = $created['agent']['id'] ?? null;
        $runId = $created['run']['id'] ?? null;

        if (! is_string($agentId) || $agentId === '' || ! is_string($runId) || $runId === '') {
            throw new AgentProviderException('invalid_response');
        }

        return new DiscoveryAgentHandle($agentId, $runId);
    }

    public function fetch(DiscoveryAgentHandle $handle): DiscoveryAgentSnapshot
    {
        $path = '/agents/'.rawurlencode($handle->agentId).'/runs/'.rawurlencode($handle->runId);
        $run = $this->send('GET', $path);
        $status = (string) ($run['status'] ?? '');

        if ($status === 'FINISHED') {
            return DiscoveryAgentSnapshot::finished((string) ($run['result'] ?? ''));
        }

        if (in_array($status, ['ERROR', 'CANCELLED', 'EXPIRED'], true)) {
            return DiscoveryAgentSnapshot::failed($status);
        }

        return DiscoveryAgentSnapshot::running();
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, ?array $body = null): array
    {
        $apiKey = trim((string) config('services.cursor.api_key'));
        if ($apiKey === '') {
            throw new AgentProviderException('missing_api_key');
        }

        $pending = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        try {
            $response = $method === 'POST'
                ? $pending->post($this->endpoint($path), $body ?? [])
                : $pending->get($this->endpoint($path));
        } catch (ConnectionException $exception) {
            throw new AgentTransientException('connection', $exception);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new AgentTransientException('http_'.$response->status());
        }

        if ($response->failed()) {
            throw new AgentProviderException('http_'.$response->status());
        }

        return $this->payload($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $data = $response->json();
        if (! is_array($data)) {
            throw new AgentProviderException('invalid_response');
        }

        return $data;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.cursor.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function repositoryUrl(string $repository): string
    {
        $repository = trim($repository);
        if (preg_match('#^https?://#i', $repository) === 1) {
            return $repository;
        }

        $repository = preg_replace('#^github\.com/#i', '', $repository) ?? $repository;

        return 'https://github.com/'.ltrim($repository, '/');
    }
}
