<?php

namespace App\Services\Agents;

final class DiscoveryAgentRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $prompt,
        public readonly string $repository,
        public readonly string $branch,
    ) {}
}
