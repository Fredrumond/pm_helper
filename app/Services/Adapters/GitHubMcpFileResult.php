<?php

namespace App\Services\Adapters;

final readonly class GitHubMcpFileResult
{
    public const KIND_DIRECTORY = 'directory';

    public const KIND_FILE = 'file';

    public const KIND_MISSING = 'missing';

    public const KIND_BINARY = 'binary';

    public const KIND_TOO_LARGE = 'too_large';

    /**
     * @param  list<array{type: string, name: string, path: string}>  $entries
     */
    public function __construct(
        public string $kind,
        public array $entries = [],
        public string $text = '',
        public ?string $mimeType = null,
    ) {}

    /**
     * @param  list<array{type: string, name: string, path: string}>  $entries
     */
    public static function directory(array $entries): self
    {
        return new self(self::KIND_DIRECTORY, entries: $entries);
    }

    public static function file(string $text, ?string $mimeType): self
    {
        return new self(self::KIND_FILE, text: $text, mimeType: $mimeType);
    }

    public static function missing(): self
    {
        return new self(self::KIND_MISSING);
    }

    public static function binary(): self
    {
        return new self(self::KIND_BINARY);
    }

    public static function tooLarge(): self
    {
        return new self(self::KIND_TOO_LARGE);
    }
}
