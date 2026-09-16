<?php

namespace App\Exceptions;

use RuntimeException;

final class ProjectDocsTooLargeException extends RuntimeException
{
    public function __construct(public readonly int $chars)
    {
        parent::__construct('Project docs exceeded the configured character limit.');
    }
}
