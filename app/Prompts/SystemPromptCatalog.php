<?php

namespace App\Prompts;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SystemPromptCatalog
{
    public function current(string $name = 'discovery'): SystemPrompt
    {
        $version = (string) config("chat.prompts.{$name}.version", 'v1');

        return $this->get($name, $version);
    }

    public function get(string $name, string $version): SystemPrompt
    {
        $this->assertValidIdentifier($name);
        $this->assertValidIdentifier($version);

        $path = resource_path("prompts/{$name}/{$version}.md");

        if (! File::exists($path)) {
            throw new RuntimeException("Prompt {$name}@{$version} não encontrado em {$path}.");
        }

        $content = rtrim((string) File::get($path));

        if ($content === '') {
            throw new RuntimeException("Prompt {$name}@{$version} está vazio.");
        }

        return new SystemPrompt(
            name: $name,
            version: $version,
            content: $content,
            hash: hash('sha256', $content),
        );
    }

    /**
     * @return list<string>
     */
    public function versions(string $name): array
    {
        $this->assertValidIdentifier($name);

        $directory = resource_path("prompts/{$name}");

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => $file->getExtension() === 'md')
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();
    }

    private function assertValidIdentifier(string $value): void
    {
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $value) !== 1) {
            throw new RuntimeException("Identificador de prompt inválido: {$value}");
        }
    }
}
