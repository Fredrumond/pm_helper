<?php

namespace App\Services;

final readonly class ProjectDocsPathsResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public string $status,
        public array $paths,
        public ?string $errorCode,
    ) {}

    /**
     * @param  list<string>  $paths
     */
    public static function ok(array $paths): self
    {
        return new self(
            status: self::STATUS_OK,
            paths: array_values($paths),
            errorCode: null,
        );
    }

    public static function failed(string $errorCode): self
    {
        return new self(
            status: self::STATUS_FAILED,
            paths: [],
            errorCode: $errorCode,
        );
    }
}
