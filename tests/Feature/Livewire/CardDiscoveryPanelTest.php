<?php

namespace Tests\Feature\Livewire;

use App\Contracts\AgentGateway;
use App\Jobs\AdvanceCardDiscovery;
use App\Livewire\CardDiscoveryPanel;
use App\Models\CardDiscovery;
use App\Models\Project;
use App\Models\User;
use App\Services\Agents\DiscoveryAgentSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesCards;
use Tests\Support\FakeAgentGateway;
use Tests\TestCase;

class CardDiscoveryPanelTest extends TestCase
{
    use CreatesCards, RefreshDatabase;

    public function test_owner_sees_the_action_only_on_an_approved_card_with_a_project(): void
    {
        config(['services.cursor.api_key' => 'super-secret-cursor-key']);
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);
        $card = $this->createCard($user, $project);

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('Anexos')
            ->assertSee('Gerar Discovery')
            ->assertSee('Nenhum Discovery gerado para este card.')
            ->assertDontSee('super-secret-cursor-key')
            ->assertDontSee('cards/'.$card->id.'/discovery.md');

        $this->actingAs($user)
            ->get(route('conversations.show', $card->conversation))
            ->assertOk()
            ->assertDontSee('Gerar Discovery');

        $draft = $this->createCard($user, $project, 'draft', ['title' => 'Rascunho sem discovery']);
        $this->actingAs($user)
            ->get(route('cards.show', $draft))
            ->assertOk()
            ->assertDontSee('Gerar Discovery')
            ->assertDontSee('Anexos');

        $withoutProject = $this->createCard($user, null, 'approved', ['title' => 'Sem projeto']);
        $this->actingAs($user)
            ->get(route('cards.show', $withoutProject))
            ->assertOk()
            ->assertDontSee('Gerar Discovery');
    }

    public function test_stranger_and_admin_cannot_open_the_card_or_the_panel(): void
    {
        Event::fake([MessageLogged::class]);
        Storage::fake('discoveries');
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, "# Segredo do discovery\n\nNao vazar.");
        CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_COMPLETED,
            'document_disk' => 'discoveries',
            'document_path' => $path,
        ]);

        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($stranger)
            ->get(route('cards.show', $card))
            ->assertForbidden()
            ->assertDontSee('Segredo do discovery');

        $this->actingAs($admin)
            ->get(route('cards.show', $card))
            ->assertForbidden()
            ->assertDontSee('Nao vazar.');

        Livewire::actingAs($stranger)
            ->test(CardDiscoveryPanel::class, ['card' => $card])
            ->assertForbidden();

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($card, $stranger): bool {
            return $log->message === 'card_discovery.forbidden'
                && $log->context['card_id'] === $card->id
                && $log->context['user_id'] === $stranger->id;
        });
    }

    public function test_generate_shows_progress_and_blocks_a_second_request(): void
    {
        Queue::fake();
        $gateway = $this->fakeAgents();
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());

        Livewire::actingAs($user)
            ->test(CardDiscoveryPanel::class, ['card' => $card])
            ->call('generate')
            ->assertSee('Solicitado')
            ->assertSee('Você pode sair desta tela')
            ->assertSee('wire:poll.5s', false)
            ->assertDontSee('Gerar Discovery')
            ->call('generate')
            ->assertHasErrors(['discovery'])
            ->assertSee('Já existe um Discovery em andamento para este card.');

        $this->assertSame(0, $gateway->starts);
        Queue::assertPushed(AdvanceCardDiscovery::class, 1);
    }

    public function test_owner_opens_rendered_markdown_without_raw_html(): void
    {
        Storage::fake('discoveries');
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, <<<'MD'
# Aviso de duplicata

Corpo do discovery.

<script>alert(1)</script>
MD);
        CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_COMPLETED,
            'document_disk' => 'discoveries',
            'document_path' => $path,
        ]);

        Livewire::actingAs($user)
            ->test(CardDiscoveryPanel::class, ['card' => $card])
            ->assertSee('Concluído')
            ->assertSee('Aviso de duplicata')
            ->assertSee('Gerar de novo')
            ->assertDontSee('Corpo do discovery.')
            ->assertDontSee('cards/'.$card->id.'/discovery.md')
            ->call('toggleDocument')
            ->assertSee('Corpo do discovery.')
            ->assertSee('Fechar')
            ->assertDontSee('<script', false);
    }

    public function test_owner_sees_the_flow_as_a_mermaid_block(): void
    {
        Storage::fake('discoveries');
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, <<<'MD'
# Fluxo do card

```mermaid
flowchart LR
    A[Card aprovado] --> B[Solicitado]
```
MD);
        CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_COMPLETED,
            'document_disk' => 'discoveries',
            'document_path' => $path,
        ]);

        Livewire::actingAs($user)
            ->test(CardDiscoveryPanel::class, ['card' => $card])
            ->call('toggleDocument')
            ->assertSee('class="mermaid"', false)
            ->assertSee('flowchart LR', false)
            ->assertDontSee('language-mermaid', false);
    }

    public function test_failed_regeneration_keeps_the_previous_attachment_visible(): void
    {
        Storage::fake('discoveries');
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, "# Aviso de duplicata\n\nVersao anterior.");
        CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_FAILED,
            'error_message' => 'O agente não concluiu o Discovery.',
            'document_disk' => 'discoveries',
            'document_path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('Falhou')
            ->assertSee('O agente não concluiu o Discovery.')
            ->assertSee('O Discovery anterior continua disponível.')
            ->assertSee('Aviso de duplicata')
            ->assertSee('Gerar de novo')
            ->assertDontSee('Versao anterior.')
            ->assertDontSee('wire:poll.5s', false);
    }

    public function test_running_regeneration_explains_that_the_previous_file_stays(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::running()];
        $user = User::factory()->create();
        $card = $this->createCard($user, Project::factory()->create());
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, "# Aviso de duplicata\n\nVersao anterior.");
        CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_COMPLETED,
            'document_disk' => 'discoveries',
            'document_path' => $path,
            'deadline_at' => now()->addMinutes(20),
        ]);

        Livewire::actingAs($user)
            ->test(CardDiscoveryPanel::class, ['card' => $card])
            ->assertSee('Gerar de novo')
            ->call('generate')
            ->assertSee('Em andamento')
            ->assertSee('O Discovery anterior continua disponível até a nova geração concluir com sucesso.')
            ->assertDontSee('Gerar de novo');
    }

    private function fakeAgents(): FakeAgentGateway
    {
        $gateway = new FakeAgentGateway;
        $this->app->instance(AgentGateway::class, $gateway);

        return $gateway;
    }
}
