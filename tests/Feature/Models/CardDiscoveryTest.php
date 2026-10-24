<?php

namespace Tests\Feature\Models;

use App\Models\CardDiscovery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CardDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_card_has_a_single_discovery_and_no_api_key_column(): void
    {
        $discovery = CardDiscovery::factory()->create();

        $this->assertFalse(Schema::hasColumn('card_discoveries', 'api_key'));
        $this->assertFalse(Schema::hasColumn('card_discoveries', 'cursor_api_key'));
        $this->assertTrue($discovery->card->discovery->is($discovery));

        $this->expectException(QueryException::class);

        CardDiscovery::query()->create([
            'card_id' => $discovery->card_id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_FAILED,
        ]);
    }

    public function test_status_labels_and_progress_flags(): void
    {
        $requested = new CardDiscovery(['status' => CardDiscovery::STATUS_REQUESTED]);
        $running = new CardDiscovery(['status' => CardDiscovery::STATUS_RUNNING]);
        $completed = new CardDiscovery(['status' => CardDiscovery::STATUS_COMPLETED]);
        $failed = new CardDiscovery(['status' => CardDiscovery::STATUS_FAILED]);

        $this->assertSame('Solicitado', $requested->statusLabel());
        $this->assertSame('Em andamento', $running->statusLabel());
        $this->assertSame('Concluído', $completed->statusLabel());
        $this->assertSame('Falhou', $failed->statusLabel());
        $this->assertTrue($requested->isInProgress());
        $this->assertTrue($running->isInProgress());
        $this->assertFalse($completed->isInProgress());
        $this->assertTrue($completed->isTerminal());
        $this->assertTrue($failed->isFailed());
        $this->assertFalse($running->isTerminal());
    }

    public function test_pending_answers_are_stored_and_reread_as_pairs(): void
    {
        $pairs = [
            ['question' => 'Onde mostrar o aviso?', 'answer' => 'Na tela de criar tarefa'],
            ['question' => 'A demanda cobre a API?', 'answer' => 'Só a web'],
        ];

        $discovery = CardDiscovery::factory()->create([
            'pending_answers' => $pairs,
        ]);

        $this->assertTrue(Schema::hasColumn('card_discoveries', 'pending_answers'));
        $this->assertSame($pairs, $discovery->fresh()->pending_answers);

        $discovery->update(['pending_answers' => null]);

        $this->assertNull($discovery->fresh()->pending_answers);
    }
}
