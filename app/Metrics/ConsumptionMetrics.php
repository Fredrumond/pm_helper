<?php

namespace App\Metrics;

use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConsumptionMetrics
{
    /**
     * @return array{
     *     calls: int,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     cached_tokens: int,
     *     cost: float,
     *     formatted_cost: string,
     *     conversations: int,
     *     completed: int
     * }
     */
    public function summary(User $user): array
    {
        $usage = $this->usagesFor($user)
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cached_tokens), 0) as cached_tokens')
            ->selectRaw('COALESCE(SUM(cost), 0) as cost')
            ->first();

        $conversations = $user->conversations()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        $cost = (float) ($usage->cost ?? 0);

        return [
            'calls' => (int) ($usage->calls ?? 0),
            'prompt_tokens' => (int) ($usage->prompt_tokens ?? 0),
            'completion_tokens' => (int) ($usage->completion_tokens ?? 0),
            'total_tokens' => (int) ($usage->total_tokens ?? 0),
            'cached_tokens' => (int) ($usage->cached_tokens ?? 0),
            'cost' => $cost,
            'formatted_cost' => LlmUsage::formatCost($cost),
            'conversations' => (int) ($conversations->total ?? 0),
            'completed' => (int) ($conversations->completed ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{model: string, calls: int, total_tokens: int, cost: float, formatted_cost: string}>
     */
    public function byModel(User $user): Collection
    {
        return $this->usagesFor($user)
            ->selectRaw('model')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cost), 0) as cost')
            ->groupBy('model')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(fn (LlmUsage $row): array => [
                'model' => $row->model,
                'calls' => (int) $row->calls,
                'total_tokens' => (int) $row->total_tokens,
                'cost' => (float) $row->cost,
                'formatted_cost' => LlmUsage::formatCost((float) $row->cost),
            ]);
    }

    /**
     * @return Collection<int, LlmUsage>
     */
    public function recent(User $user, int $limit = 20): Collection
    {
        return $this->usagesFor($user)
            ->with('conversation:id,title,user_id')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder<LlmUsage>
     */
    private function usagesFor(User $user): Builder
    {
        return LlmUsage::query()->whereIn(
            'conversation_id',
            $user->conversations()->select('id')
        );
    }
}
