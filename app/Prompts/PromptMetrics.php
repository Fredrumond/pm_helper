<?php

namespace App\Prompts;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;

class PromptMetrics
{
    /**
     * Compara versões do prompt por conclusão de card, tokens e custo.
     *
     * @return Collection<int, array{
     *     version: string,
     *     conversations: int,
     *     completed: int,
     *     completion_rate: float,
     *     calls: int,
     *     avg_tokens: float,
     *     avg_cost: float,
     *     avg_messages: float
     * }>
     */
    public function compare(?User $user = null): Collection
    {
        $conversations = Conversation::query()
            ->whereNotNull('prompt_version')
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->withCount('messages')
            ->withCount('llmUsages as usage_calls')
            ->withSum('llmUsages as usage_tokens', 'total_tokens')
            ->withSum('llmUsages as usage_cost', 'cost')
            ->get()
            ->groupBy('prompt_version');

        return $conversations->map(function (Collection $group, string $version): array {
            $count = $group->count();
            $completed = $group->where('status', 'completed')->count();

            return [
                'version' => $version,
                'conversations' => $count,
                'completed' => $completed,
                'completion_rate' => $count > 0 ? round($completed / $count, 3) : 0.0,
                'calls' => (int) $group->sum('usage_calls'),
                'avg_tokens' => round((float) $group->avg(fn (Conversation $conversation) => (int) ($conversation->usage_tokens ?? 0)), 1),
                'avg_cost' => round((float) $group->avg(fn (Conversation $conversation) => (float) ($conversation->usage_cost ?? 0)), 8),
                'avg_messages' => round((float) $group->avg('messages_count'), 1),
            ];
        })->values();
    }
}
