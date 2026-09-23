<?php

namespace App\Providers;

use App\Contracts\LlmGateway;
use App\Contracts\ProjectDocsGateway;
use App\Models\LlmModel;
use App\Models\User;
use App\Services\Adapters\GitHubMcpProjectDocsGateway;
use App\Services\Adapters\OpenAiAdapter;
use App\Services\Adapters\OpenRouterAdapter;
use App\Services\LlmRouter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmGateway::class, function ($app) {
            $adapters = [];

            if (trim((string) config('services.openai.api_key')) !== '') {
                $adapters[LlmModel::PROVIDER_OPENAI] = $app->make(OpenAiAdapter::class);
            }

            return new LlmRouter(
                default: $app->make(OpenRouterAdapter::class),
                adapters: $adapters,
            );
        });

        $this->app->bind(ProjectDocsGateway::class, GitHubMcpProjectDocsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        if ($this->app->runningInConsole()) {
            return;
        }

        $forwardedProto = request()->headers->get('X-Forwarded-Proto');

        if ($forwardedProto === 'https' || str_contains((string) $forwardedProto, 'https')) {
            URL::forceScheme('https');
        }
    }
}
