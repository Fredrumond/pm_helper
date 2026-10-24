<?php

namespace App\Contracts;

use App\Services\Agents\DiscoveryAgentHandle;
use App\Services\Agents\DiscoveryAgentRequest;
use App\Services\Agents\DiscoveryAgentSnapshot;

interface AgentGateway
{
    public function provider(): string;

    public function start(DiscoveryAgentRequest $request): DiscoveryAgentHandle;

    public function fetch(DiscoveryAgentHandle $handle): DiscoveryAgentSnapshot;
}
