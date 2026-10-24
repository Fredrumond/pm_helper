<?php

namespace Tests\Support;

use App\Contracts\AgentGateway;
use App\Services\Agents\DiscoveryAgentHandle;
use App\Services\Agents\DiscoveryAgentRequest;
use App\Services\Agents\DiscoveryAgentSnapshot;
use Throwable;

final class FakeAgentGateway implements AgentGateway
{
    public int $starts = 0;

    public int $fetches = 0;

    public ?DiscoveryAgentRequest $lastRequest = null;

    public ?Throwable $startException = null;

    public ?Throwable $fetchException = null;

    /** @var list<DiscoveryAgentSnapshot> */
    public array $snapshots = [];

    public function provider(): string
    {
        return 'cursor';
    }

    public function start(DiscoveryAgentRequest $request): DiscoveryAgentHandle
    {
        $this->starts++;
        $this->lastRequest = $request;

        if ($this->startException !== null) {
            throw $this->startException;
        }

        return new DiscoveryAgentHandle('agent-1', 'run-1');
    }

    public function fetch(DiscoveryAgentHandle $handle): DiscoveryAgentSnapshot
    {
        $this->fetches++;

        if ($this->fetchException !== null) {
            throw $this->fetchException;
        }

        return array_shift($this->snapshots) ?? DiscoveryAgentSnapshot::running();
    }
}
