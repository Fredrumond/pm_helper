<?php

namespace App\Exceptions;

use RuntimeException;

final class LlmModelInUseException extends RuntimeException
{
    /**
     * @param  list<string>  $prompts
     */
    public function __construct(public readonly string $modelId, public readonly array $prompts)
    {
        $list = implode(', ', $prompts);

        parent::__construct("O modelo [{$modelId}] não pode ser inativado: está em uso pelo(s) prompt(s): {$list}.");
    }
}
