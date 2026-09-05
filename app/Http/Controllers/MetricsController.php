<?php

namespace App\Http\Controllers;

use App\Metrics\ConsumptionMetrics;
use App\Prompts\PromptMetrics;
use App\Prompts\SystemPromptCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MetricsController extends Controller
{
    public function index(
        ConsumptionMetrics $consumptionMetrics,
        PromptMetrics $promptMetrics,
        SystemPromptCatalog $catalog,
    ): View {
        $user = Auth::user();

        return view('metrics.index', [
            'summary' => $consumptionMetrics->summary($user),
            'byModel' => $consumptionMetrics->byModel($user),
            'recent' => $consumptionMetrics->recent($user),
            'promptComparison' => $promptMetrics->compare($user),
            'promptVersions' => $catalog->versions('discovery'),
            'currentPromptVersion' => (string) config('chat.prompts.discovery.version', 'v1'),
        ]);
    }
}
