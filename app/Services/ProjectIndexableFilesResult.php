<?php

namespace App\Services;

final readonly class ProjectIndexableFilesResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  list<array{path: string, content: string}>  $files
     */
    public function __construct(
        public string $status,
        public array $files,
        public ?string $errorCode,
    ) {}

    /**
     * @param  list<array{path: string, content: string}>  $files
     */
    public static function ok(array $files): self
    {
        return new self(self::STATUS_OK, $files, null);
    }

    public static function empty(): self
    {
        return new self(self::STATUS_EMPTY, [], null);
    }

    public static function failed(string $errorCode): self
    {
        return new self(self::STATUS_FAILED, [], $errorCode);
    }
}
