<?php

namespace Tests\Feature\Services;

use App\Services\Adapters\CursorAgentAdapter;
use App\Services\Agents\AgentProviderException;
use App\Services\Agents\AgentTransientException;
use App\Services\Agents\DiscoveryAgentHandle;
use App\Services\Agents\DiscoveryAgentRequest;
use App\Services\Agents\DiscoveryAgentSnapshot;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CursorAgentAdapterTest extends TestCase
{
    private CursorAgentAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cursor.api_key' => 'cursor-test-key',
            'services.cursor.base_url' => 'https://api.cursor.com/v1',
            'services.cursor.model' => '',
        ]);

        Http::preventStrayRequests();
        $this->adapter = new CursorAgentAdapter;
    }

    public function test_start_posts_a_read_only_agent_without_putting_the_key_in_the_body(): void
    {
        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'agent_1'],
                'run' => ['id' => 'run_1'],
            ]),
        ]);

        $handle = $this->adapter->start(new DiscoveryAgentRequest(
            name: 'Discovery: Avisar card parecido',
            prompt: 'Somente leitura. Título: Avisar card parecido',
            repository: 'acme/mobile-app',
            branch: 'develop',
        ));

        $this->assertSame('agent_1', $handle->agentId);
        $this->assertSame('run_1', $handle->runId);

        Http::assertSent(function (Request $request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->method() === 'POST'
                && $request->url() === 'https://api.cursor.com/v1/agents'
                && $authorization === 'Basic '.base64_encode('cursor-test-key:')
                && $request['autoCreatePR'] === false
                && $request['repos'][0]['url'] === 'https://github.com/acme/mobile-app'
                && $request['repos'][0]['startingRef'] === 'develop'
                && ! array_key_exists('model', $request->data())
                && ! str_contains((string) json_encode($request->data()), 'cursor-test-key');
        });
    }

    public function test_start_sends_the_configured_model_and_keeps_a_full_repository_url(): void
    {
        config(['services.cursor.model' => 'composer-2']);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'agent_2'],
                'run' => ['id' => 'run_2'],
            ]),
        ]);

        $this->adapter->start(new DiscoveryAgentRequest(
            name: 'Discovery',
            prompt: 'prompt',
            repository: 'https://github.com/acme/mobile-app',
            branch: 'main',
        ));

        Http::assertSent(function (Request $request): bool {
            return ($request['model']['id'] ?? null) === 'composer-2'
                && $request['repos'][0]['url'] === 'https://github.com/acme/mobile-app';
        });
    }

    public function test_missing_key_does_not_call_the_api(): void
    {
        config(['services.cursor.api_key' => '']);
        Http::fake();

        try {
            $this->adapter->start(new DiscoveryAgentRequest('n', 'p', 'acme/app', 'main'));
            $this->fail('A chave ausente deveria falhar antes da rede.');
        } catch (AgentProviderException $exception) {
            $this->assertSame('missing_api_key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_unauthorized_is_terminal_and_server_error_is_transient(): void
    {
        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::sequence()
                ->push('no', 401)
                ->push('down', 503),
        ]);

        try {
            $this->adapter->start(new DiscoveryAgentRequest('n', 'p', 'acme/app', 'main'));
            $this->fail('401 deveria ser falha definitiva.');
        } catch (AgentProviderException $exception) {
            $this->assertSame('http_401', $exception->getMessage());
        }

        try {
            $this->adapter->start(new DiscoveryAgentRequest('n', 'p', 'acme/app', 'main'));
            $this->fail('503 deveria ser falha transitória.');
        } catch (AgentTransientException $exception) {
            $this->assertSame('http_503', $exception->getMessage());
        }
    }

    public function test_fetch_maps_terminal_and_running_statuses(): void
    {
        Http::fake([
            'https://api.cursor.com/v1/agents/agent_1/runs/run_1' => Http::sequence()
                ->push(['status' => 'RUNNING'])
                ->push(['status' => 'FINISHED', 'result' => "# Discovery\n\nTexto"])
                ->push(['status' => 'ERROR'])
                ->push(['status' => 'CANCELLED'])
                ->push(['status' => 'EXPIRED']),
        ]);

        $handle = new DiscoveryAgentHandle('agent_1', 'run_1');

        $this->assertTrue($this->adapter->fetch($handle)->isRunning());

        $finished = $this->adapter->fetch($handle);
        $this->assertTrue($finished->isFinished());
        $this->assertSame("# Discovery\n\nTexto", $finished->markdown);

        $error = $this->adapter->fetch($handle);
        $this->assertSame(DiscoveryAgentSnapshot::FAILED, $error->state);
        $this->assertSame('ERROR', $error->detail);
        $this->assertSame('CANCELLED', $this->adapter->fetch($handle)->detail);
        $this->assertSame('EXPIRED', $this->adapter->fetch($handle)->detail);
    }
}
