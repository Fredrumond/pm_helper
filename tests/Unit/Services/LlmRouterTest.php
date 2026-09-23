<?php

namespace Tests\Unit\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;
use App\Models\LlmModel;
use App\Services\Adapters\OpenAiAdapter;
use App\Services\LlmRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class LlmRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegates_to_default_when_model_is_missing_inactive_or_openrouter(): void
    {
        CatalogModels::seed([
            ['id' => 'nvidia/nemotron:free', 'name' => 'Nemotron', 'provider' => LlmModel::PROVIDER_OPENROUTER],
            [
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'active' => false,
                'price_input' => 2.5,
                'price_cached' => 1.25,
                'price_output' => 10,
            ],
        ]);

        $default = new FakeLlmGateway('default');
        $openai = new FakeLlmGateway('openai');
        $router = new LlmRouter($default, [LlmModel::PROVIDER_OPENAI => $openai]);
        $conversation = new Conversation;

        $this->assertSame('default:chat', $router->chat($conversation, 'nvidia/nemotron:free'));
        $this->assertSame('default:chat', $router->chat($conversation, 'gpt-4o'));
        $this->assertSame('default:chat', $router->chat($conversation, 'unknown/model'));
        $this->assertSame('default:chat', $router->chat($conversation, null));
        $this->assertNull($openai->lastConversation);
    }

    public function test_routes_active_openai_provider_to_the_openai_adapter(): void
    {
        CatalogModels::seed([[
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]]);

        $default = new FakeLlmGateway('default');
        $openai = new FakeLlmGateway('openai');
        $router = new LlmRouter($default, [LlmModel::PROVIDER_OPENAI => $openai]);
        $conversation = new Conversation;
        $messages = [['role' => 'user', 'content' => 'índice']];

        $this->assertSame('openai:chat', $router->chat($conversation, 'gpt-4o-mini'));
        $this->assertSame('openai:card', $router->generateCard($conversation, 'resumo', 'gpt-4o-mini'));
        $this->assertSame('openai:complete', $router->completePrompt($messages, 'gpt-4o-mini', 'docs_retrieval', $conversation));
        $this->assertSame($conversation, $openai->lastConversation);
        $this->assertSame('resumo', $openai->lastSummary);
        $this->assertSame($messages, $openai->lastMessages);
        $this->assertSame('docs_retrieval', $openai->lastStep);
        $this->assertNull($default->lastConversation);
    }

    public function test_openai_provider_falls_back_to_default_when_adapter_is_not_registered(): void
    {
        CatalogModels::seed([[
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]]);

        $default = new FakeLlmGateway('default');
        $router = new LlmRouter($default);
        $conversation = new Conversation;

        $this->assertSame('default:chat', $router->chat($conversation, 'gpt-4o-mini'));
        $this->assertSame('gpt-4o-mini', $default->lastModel);
    }

    public function test_complete_prompt_propagates_docs_briefing_step_on_the_default_adapter(): void
    {
        CatalogModels::seed([[
            'id' => 'nvidia/nemotron:free',
            'name' => 'Nemotron',
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]]);

        $default = new FakeLlmGateway('default');
        $router = new LlmRouter($default, [LlmModel::PROVIDER_OPENAI => new FakeLlmGateway('openai')]);
        $messages = [['role' => 'user', 'content' => 'docs']];
        $conversation = new Conversation;

        $this->assertSame('default:complete', $router->completePrompt($messages, 'nvidia/nemotron:free', 'docs_briefing', $conversation));
        $this->assertSame('docs_briefing', $default->lastStep);
        $this->assertSame($messages, $default->lastMessages);
        $this->assertSame($conversation, $default->lastConversation);
    }

    public function test_app_service_provider_registers_openai_adapter_by_provider_when_key_exists(): void
    {
        config([
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.openai.api_key' => 'sk-test',
        ]);

        $gateway = $this->app->make(LlmGateway::class);

        $this->assertInstanceOf(LlmRouter::class, $gateway);

        $adapters = (new ReflectionProperty(LlmRouter::class, 'adapters'))->getValue($gateway);

        $this->assertIsArray($adapters);
        $this->assertArrayHasKey(LlmModel::PROVIDER_OPENAI, $adapters);
        $this->assertInstanceOf(OpenAiAdapter::class, $adapters[LlmModel::PROVIDER_OPENAI]);
        $this->assertArrayNotHasKey('gpt-', $adapters);
        $this->assertArrayNotHasKey('o1', $adapters);
        $this->assertArrayNotHasKey('o3', $adapters);
        $this->assertArrayNotHasKey('o4', $adapters);
    }

    public function test_app_service_provider_skips_openai_adapter_without_api_key(): void
    {
        config([
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.openai.api_key' => '',
        ]);

        $gateway = $this->app->make(LlmGateway::class);
        $adapters = (new ReflectionProperty(LlmRouter::class, 'adapters'))->getValue($gateway);

        $this->assertSame([], $adapters);
    }
}

class FakeLlmGateway implements LlmGateway
{
    public ?Conversation $lastConversation = null;

    public ?string $lastModel = null;

    public ?string $lastSummary = null;

    /** @var list<array{role: string, content: string}>|null */
    public ?array $lastMessages = null;

    public ?string $lastStep = null;

    public function __construct(private readonly string $name) {}

    public function chat(Conversation $conversation, ?string $model = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        return "{$this->name}:chat";
    }

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastSummary = $summary;
        $this->lastModel = $model;

        return "{$this->name}:card";
    }

    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string {
        $this->lastMessages = $messages;
        $this->lastModel = $model;
        $this->lastStep = $step;
        $this->lastConversation = $conversation;

        return "{$this->name}:complete";
    }
}
