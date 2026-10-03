<?php

namespace Tests\Feature\Livewire;

use App\Contracts\LlmGateway;
use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Livewire\ExploreChat;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\User;
use App\Prompts\SystemPromptCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CatalogModels;
use Tests\Support\FakeLlmGateway;
use Tests\TestCase;

class ExploreChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_history_through_complete_prompt_with_fixed_openai_model(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $prompt = (new SystemPromptCatalog)->current('explore');
        $llm = $this->bindFakeLlmGateway()->queueComplete('Resposta livre do modelo.');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Como funciona o checkout?')
            ->call('sendMessage')
            ->assertSee('Como funciona o checkout?')
            ->assertSee('Resposta livre do modelo.');

        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame(ExploreChat::MODEL, $llm->lastModel);
        $this->assertSame('explore', $llm->completeCalls[0]['step']);
        $this->assertTrue($conversation->is($llm->completeCalls[0]['conversation']));
        $this->assertSame(
            [
                ['role' => 'system', 'content' => $prompt->content],
                ['role' => 'user', 'content' => 'Como funciona o checkout?'],
            ],
            $llm->completeCalls[0]['messages'],
        );
        $this->assertStringNotContainsString('INTERVIEW', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringNotContainsString('CARD_JSON', $llm->completeCalls[0]['messages'][0]['content']);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Como funciona o checkout?',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Resposta livre do modelo.',
        ]);
        $this->assertSame('Como funciona o checkout?', $conversation->fresh()->title);
    }

    public function test_router_calls_openai_gpt_4o_mini_and_records_usage(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-explore',
            'services.openai.model' => 'gpt-4o-mini',
        ]);
        CatalogModels::seedGpt4oMiniPrice();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-explore-1',
                'model' => 'gpt-4o-mini',
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                    'total_tokens' => 20,
                ],
                'choices' => [
                    ['message' => ['content' => 'O checkout cobra no cartão salvo.']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $prompt = (new SystemPromptCatalog)->current('explore');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Como cobra o checkout?')
            ->call('sendMessage')
            ->assertSee('O checkout cobra no cartão salvo.');

        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'chatcmpl-explore-1',
            'step' => 'explore',
            'prompt_version' => 'v1',
            'prompt_hash' => $prompt->hash,
            'total_tokens' => 20,
        ]);

        Http::assertSent(function (Request $request) use ($prompt): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0] === ['role' => 'system', 'content' => $prompt->content]
                && $request['messages'][1] === ['role' => 'user', 'content' => 'Como cobra o checkout?'];
        });
    }

    public function test_ignores_blank_input(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $llm = $this->bindFakeLlmGateway();

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', '   ')
            ->call('sendMessage');

        $this->assertSame([], $llm->completeCalls);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_shows_llm_error_in_the_thread(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $this->bindFakeLlmGateway()->queueComplete(new \RuntimeException('HTTP 401: chave inválida'));

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Oi')
            ->call('sendMessage')
            ->assertSee('Erro LLM')
            ->assertSee('HTTP 401: chave inválida');
    }

    public function test_shows_soft_unavailable_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $this->bindFakeLlmGateway()->queueComplete(
            new LlmTemporarilyUnavailableException('Não consegui continuar agora. Envie a mensagem novamente em alguns instantes.')
        );

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Oi')
            ->call('sendMessage')
            ->assertSee('Não consegui continuar agora.');
    }

    public function test_uses_document_rag_when_chunks_have_embeddings(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [1.0, 0.0]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $document = Document::factory()->create(['name' => 'missao.md']);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Há exatamente 42 quilos de minério Lumina.',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $llm = $this->bindFakeLlmGateway()->queueComplete('Há exatamente 42 quilos.');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Quanto pesava o minério Lumina?')
            ->call('sendMessage')
            ->assertSee('Há exatamente 42 quilos.');

        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame(ExploreChat::MODEL, $llm->lastModel);
        $this->assertSame('rag', $llm->completeCalls[0]['step']);
        $this->assertTrue($conversation->is($llm->completeCalls[0]['conversation']));
        $this->assertStringContainsString('Responda usando somente o contexto.', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringContainsString('Há exatamente 42 quilos de minério Lumina.', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringContainsString('Quanto pesava o minério Lumina?', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Há exatamente 42 quilos.',
        ]);
    }

    public function test_selecting_a_project_before_the_first_message_persists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Checkout']);
        $conversation = Conversation::createExploreFor($user);

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->assertSet('selectedProjectId', null)
            ->assertSee('Sem projeto')
            ->call('selectProject', $project->id)
            ->assertSet('selectedProjectId', $project->id)
            ->assertSee('Checkout');

        $this->assertSame($project->id, $conversation->fresh()->project_id);
    }

    public function test_project_cannot_change_after_the_first_message(): void
    {
        $user = User::factory()->create();
        $first = Project::factory()->create(['name' => 'Checkout']);
        $second = Project::factory()->create(['name' => 'Mobile']);
        $conversation = Conversation::createExploreFor($user);
        $conversation->update(['project_id' => $first->id]);

        $llm = $this->bindFakeLlmGateway()->queueComplete('Resposta livre.');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'Oi')
            ->call('sendMessage')
            ->call('selectProject', $second->id)
            ->assertSet('selectedProjectId', $first->id)
            ->assertSee('Checkout');

        $this->assertSame($first->id, $conversation->fresh()->project_id);
        $this->assertNotSame([], $llm->completeCalls);
    }

    public function test_without_project_rag_uses_only_unscoped_documents(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [1.0, 0.0]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);
        $project = Project::factory()->create();
        $manual = Document::factory()->create(['name' => 'manual.md', 'project_id' => null]);
        $ofProject = Document::factory()->create(['name' => 'projeto.md', 'project_id' => $project->id]);
        DocumentChunk::factory()->create([
            'document_id' => $manual->id,
            'content' => 'Resposta do documento manual.',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $ofProject->id,
            'content' => 'Resposta do documento do projeto.',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $llm = $this->bindFakeLlmGateway()->queueComplete('Veio do manual.');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->set('input', 'O que diz o documento?')
            ->call('sendMessage')
            ->assertSee('Veio do manual.');

        $this->assertSame('rag', $llm->completeCalls[0]['step']);
        $this->assertStringContainsString('Resposta do documento manual.', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringNotContainsString('Resposta do documento do projeto.', $llm->completeCalls[0]['messages'][0]['content']);
    }

    public function test_with_project_rag_uses_only_that_projects_documents(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [1.0, 0.0]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Checkout']);
        $conversation = Conversation::createExploreFor($user);
        $conversation->update(['project_id' => $project->id]);
        $manual = Document::factory()->create(['name' => 'manual.md', 'project_id' => null]);
        $ofProject = Document::factory()->create(['name' => 'projeto.md', 'project_id' => $project->id]);
        DocumentChunk::factory()->create([
            'document_id' => $manual->id,
            'content' => 'Resposta do documento manual.',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $ofProject->id,
            'content' => 'Resposta do documento do projeto.',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $llm = $this->bindFakeLlmGateway()->queueComplete('Veio do projeto.');

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->assertSee('Checkout')
            ->set('input', 'O que diz o documento?')
            ->call('sendMessage')
            ->assertSee('Veio do projeto.');

        $this->assertSame('rag', $llm->completeCalls[0]['step']);
        $this->assertStringContainsString('Resposta do documento do projeto.', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringNotContainsString('Resposta do documento manual.', $llm->completeCalls[0]['messages'][0]['content']);
    }

    public function test_rejects_another_users_explore_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::createExploreFor($owner);

        Livewire::actingAs($other)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->assertForbidden();
    }

    public function test_rejects_interview_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create([
            'title' => 'Nova conversa',
            'prompt_name' => 'interview',
        ]);

        Livewire::actingAs($user)
            ->test(ExploreChat::class, ['conversation' => $conversation])
            ->assertNotFound();
    }

    public function test_explore_conversations_do_not_appear_in_the_interview_list(): void
    {
        $user = User::factory()->create();
        $interview = $user->conversations()->create([
            'title' => 'Checkout digital',
        ]);
        Conversation::createExploreFor($user)->update([
            'title' => 'Pergunta solta do explorar',
        ]);

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('Checkout digital')
            ->assertDontSee('Pergunta solta do explorar');

        $this->assertTrue($interview->is($user->conversations()->interview()->first()));
    }

    private function bindFakeLlmGateway(): FakeLlmGateway
    {
        $fake = new FakeLlmGateway;
        $this->app->instance(LlmGateway::class, $fake);

        return $fake;
    }
}
