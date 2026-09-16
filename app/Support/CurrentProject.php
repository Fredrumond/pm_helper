<?php

namespace App\Support;

use App\Models\Project;

class CurrentProject
{
    public const SESSION_KEY = 'current_project_id';

    /**
     * Id do projeto ativo na sessão, ou null se a seleção estiver ausente ou inválida.
     * Ponto único para o MCP (e qualquer outro consumidor) ler a seleção do PM.
     */
    public static function id(): ?int
    {
        self::forgetIfInactive();

        $selectedId = session(self::SESSION_KEY);

        return $selectedId === null ? null : (int) $selectedId;
    }

    public static function select(Project $project): void
    {
        session([self::SESSION_KEY => $project->id]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function forgetIfInactive(): void
    {
        $selectedId = session(self::SESSION_KEY);

        if ($selectedId === null) {
            return;
        }

        if (! Project::query()->whereKey($selectedId)->exists()) {
            self::forget();
        }
    }
}
