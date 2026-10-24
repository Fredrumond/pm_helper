<?php

namespace App\Services\Agents;

final class DiscoveryAgentSnapshot
{
    public const RUNNING = 'running';

    public const FINISHED = 'finished';

    public const FAILED = 'failed';

    private function __construct(
        public readonly string $state,
        public readonly ?string $markdown = null,
        public readonly ?string $detail = null,
    ) {}

    public static function running(): self
    {
        return new self(self::RUNNING);
    }

    public static function finished(string $markdown): self
    {
        return new self(self::FINISHED, $markdown);
    }

    public static function failed(string $detail): self
    {
        return new self(self::FAILED, null, $detail);
    }

    public function isRunning(): bool
    {
        return $this->state === self::RUNNING;
    }

    public function isFinished(): bool
    {
        return $this->state === self::FINISHED;
    }
}
