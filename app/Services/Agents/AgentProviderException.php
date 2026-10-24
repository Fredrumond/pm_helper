<?php

namespace App\Services\Agents;

use RuntimeException;
use Throwable;

final class AgentProviderException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
