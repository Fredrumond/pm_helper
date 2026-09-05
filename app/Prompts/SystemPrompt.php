<?php

namespace App\Prompts;

final readonly class SystemPrompt
{
    public function __construct(
        public string $name,
        public string $version,
        public string $content,
        public string $hash,
    ) {}

    public function identifier(): string
    {
        return "{$this->name}@{$this->version}";
    }
}
