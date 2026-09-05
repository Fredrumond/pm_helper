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

        $mode = (string) config('chat.mode', 'interview');

        return view('metrics.index', [
            'summary' => $consumptionMetrics->summary($user),
            'byModel' => $consumptionMetrics->byModel($user),
            'recent' => $consumptionMetrics->recent($user),
            'promptComparison' => $promptMetrics->compare($user),
            'promptSteps' => $promptMetrics->compareByStep($user),
            'promptVersions' => $catalog->versions($mode),
            'currentPromptVersion' => (string) config("chat.prompts.{$mode}.version", 'v1'),
            'currentMode' => $mode,
        ]);
    }
}
