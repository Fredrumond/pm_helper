<?php

namespace Tests\Feature\Config;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAppConfigTest extends TestCase
{
    public function test_reads_github_app_env_without_outbound_http(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $this->assertSame('', config('services.github_app.app_id'));
        $this->assertSame('', config('services.github_app.private_key'));
        $this->assertSame('', config('services.github_app.installation_id'));

        $this->get('/')->assertRedirect(route('conversations.index'));

        Http::assertNothingSent();
    }

    public function test_github_app_config_keys_match_documented_env_names(): void
    {
        $this->assertSame(env('GITHUB_APP_ID', ''), config('services.github_app.app_id'));
        $this->assertSame(env('GITHUB_APP_PRIVATE_KEY', ''), config('services.github_app.private_key'));
        $this->assertSame(env('GITHUB_APP_INSTALLATION_ID', ''), config('services.github_app.installation_id'));
    }
}
