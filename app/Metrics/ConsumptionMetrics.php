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
        $usages = $this->usagesFor($user)->get();

        $conversations = $user->conversations()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        $cost = (float) $usages->sum(fn (LlmUsage $usage) => $usage->effectiveCost());

        return [
            'calls' => $usages->count(),
            'prompt_tokens' => (int) $usages->sum('prompt_tokens'),
            'completion_tokens' => (int) $usages->sum('completion_tokens'),
            'total_tokens' => (int) $usages->sum('total_tokens'),
            'cached_tokens' => (int) $usages->sum('cached_tokens'),
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
            ->get()
            ->groupBy('model')
            ->map(function (Collection $group, string $model): array {
                $cost = (float) $group->sum(fn (LlmUsage $usage) => $usage->effectiveCost());
                $estimated = $group->contains(fn (LlmUsage $usage) => $usage->isEstimatedCost());

                return [
                    'model' => $model,
                    'calls' => $group->count(),
                    'total_tokens' => (int) $group->sum('total_tokens'),
                    'cost' => $cost,
                    'estimated' => $estimated,
                    'formatted_cost' => LlmUsage::formatCost($cost),
                ];
            })
            ->sortByDesc('total_tokens')
            ->values();
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
