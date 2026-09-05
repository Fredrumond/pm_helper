<?php

namespace App\Prompts;

use App\Models\Conversation;
use App\Models\LlmUsage;
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

    /**
     * Compara consumo da pipeline por step e versão do prompt.
     *
     * @return Collection<int, array{
     *     step: string,
     *     version: string,
     *     conversations: int,
     *     calls: int,
     *     avg_tokens: float,
     *     avg_cost: float,
     *     total_tokens: int,
     *     total_cost: float
     * }>
     */
    public function compareByStep(?User $user = null): Collection
    {
        $usages = LlmUsage::query()
            ->whereNotNull('step')
            ->when($user, function ($query) use ($user) {
                $query->whereIn(
                    'conversation_id',
                    Conversation::query()->where('user_id', $user->id)->select('id')
                );
            })
            ->get()
            ->groupBy(fn (LlmUsage $usage) => $usage->step.'@'.($usage->prompt_version ?? ''));

        return $usages->map(function (Collection $group, string $key): array {
            [$step, $version] = explode('@', $key, 2);

            return [
                'step' => $step,
                'version' => $version,
                'conversations' => $group->pluck('conversation_id')->unique()->count(),
                'calls' => $group->count(),
                'avg_tokens' => round((float) $group->avg('total_tokens'), 1),
                'avg_cost' => round((float) $group->avg(fn (LlmUsage $usage) => (float) $usage->cost), 8),
                'total_tokens' => (int) $group->sum('total_tokens'),
                'total_cost' => round((float) $group->sum(fn (LlmUsage $usage) => (float) $usage->cost), 8),
            ];
        })->sortBy([
            ['step', 'asc'],
            ['version', 'asc'],
        ])->values();
    }
}
