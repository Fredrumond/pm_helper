<?php

namespace App\Services\Agents;

final class DiscoveryAgentHandle
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $runId,
    ) {}
}
