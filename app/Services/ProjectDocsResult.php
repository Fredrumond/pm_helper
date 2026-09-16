<?php

namespace App\Services;

final readonly class ProjectDocsResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_TOO_LARGE = 'too_large';

    public const STATUS_FAILED = 'failed';

    public const ERROR_INVALID_REPO = 'invalid_repo';

    public const ERROR_INVALID_REF = 'invalid_ref';

    public const ERROR_AUTH = 'auth';

    public const ERROR_TIMEOUT = 'timeout';

    public const ERROR_RATE_LIMIT = 'rate_limit';

    public const ERROR_MCP = 'mcp';

    public const ERROR_MISSING_CREDENTIALS = 'missing_credentials';

    public function __construct(
        public string $status,
        public string $content,
        public int $filesRead,
        public int $skippedNonText,
        public int $chars,
        public ?string $errorCode,
        public ?string $briefing = null,
    ) {}

    public static function ok(string $content, int $filesRead, int $skippedNonText): self
    {
        return new self(
            status: self::STATUS_OK,
            content: $content,
            filesRead: $filesRead,
            skippedNonText: $skippedNonText,
            chars: mb_strlen($content, 'UTF-8'),
            errorCode: null,
        );
    }

    public static function empty(int $skippedNonText = 0): self
    {
        return new self(
            status: self::STATUS_EMPTY,
            content: '',
            filesRead: 0,
            skippedNonText: $skippedNonText,
            chars: 0,
            errorCode: null,
        );
    }

    public static function tooLarge(int $filesRead, int $skippedNonText, int $chars): self
    {
        return new self(
            status: self::STATUS_TOO_LARGE,
            content: '',
            filesRead: $filesRead,
            skippedNonText: $skippedNonText,
            chars: $chars,
            errorCode: null,
        );
    }

    public static function failed(
        string $errorCode,
        int $filesRead = 0,
        int $skippedNonText = 0,
        int $chars = 0,
    ): self {
        return new self(
            status: self::STATUS_FAILED,
            content: '',
            filesRead: $filesRead,
            skippedNonText: $skippedNonText,
            chars: $chars,
            errorCode: $errorCode,
        );
    }

    public function withBriefing(?string $briefing): self
    {
        return new self(
            status: $this->status,
            content: $this->content,
            filesRead: $this->filesRead,
            skippedNonText: $this->skippedNonText,
            chars: $this->chars,
            errorCode: $this->errorCode,
            briefing: $briefing,
        );
    }
}
