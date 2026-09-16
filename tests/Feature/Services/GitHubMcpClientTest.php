<?php

namespace Tests\Feature\Services;

use App\Services\Adapters\GitHubMcpClient;
use App\Services\Adapters\GitHubMcpFileResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubMcpClientTest extends TestCase
{
    public function test_get_file_contents_sends_ref_when_branch_is_provided(): void
    {
        $this->fakeMcp();

        $client = new GitHubMcpClient;
        $client->initialize('ghs_test');
        $result = $client->getFileContents('fredrumond', 'rapidtask', 'docs', 'develop');

        $this->assertSame(GitHubMcpFileResult::KIND_MISSING, $result->kind);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return ($payload['method'] ?? null) === 'tools/call'
                && ($payload['params']['name'] ?? null) === 'get_file_contents'
                && ($payload['params']['arguments']['owner'] ?? null) === 'fredrumond'
                && ($payload['params']['arguments']['repo'] ?? null) === 'rapidtask'
                && ($payload['params']['arguments']['path'] ?? null) === 'docs'
                && ($payload['params']['arguments']['ref'] ?? null) === 'develop';
        });
    }

    public function test_get_file_contents_omits_ref_when_branch_is_empty(): void
    {
        $this->fakeMcp();

        $client = new GitHubMcpClient;
        $client->initialize('ghs_test');
        $client->getFileContents('fredrumond', 'rapidtask', 'docs');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $arguments = $payload['params']['arguments'] ?? null;

            return ($payload['method'] ?? null) === 'tools/call'
                && is_array($arguments)
                && ! array_key_exists('ref', $arguments);
        });
    }

    private function fakeMcp(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $payload = $request->data();
            $method = $payload['method'] ?? '';

            if ($method === 'initialize') {
                return Http::response(
                    [
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'result' => [
                            'protocolVersion' => '2025-03-26',
                            'capabilities' => (object) [],
                            'serverInfo' => ['name' => 'github', 'version' => '1'],
                        ],
                    ],
                    200,
                    [
                        'Content-Type' => 'application/json',
                        'Mcp-Session-Id' => 'sess-1',
                    ],
                );
            }

            if (! array_key_exists('id', $payload)) {
                return Http::response('', 202);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $payload['id'],
                'result' => [
                    'isError' => true,
                    'content' => [
                        ['type' => 'text', 'text' => 'path did not point to a file or directory'],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']);
        });
    }
}
