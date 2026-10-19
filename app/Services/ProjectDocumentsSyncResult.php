<?php

namespace App\Services;

final readonly class ProjectDocumentsSyncResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_FAILED = 'failed';

    private function __construct(public string $status) {}

    public static function ok(): self
    {
        return new self(self::STATUS_OK);
    }

    public static function empty(): self
    {
        return new self(self::STATUS_EMPTY);
    }

    public static function failed(): self
    {
        return new self(self::STATUS_FAILED);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
