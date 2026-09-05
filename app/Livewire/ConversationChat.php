<?php

namespace App\Livewire;

use App\Models\Card;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\CardParserService;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class ConversationChat extends Component
{
    public Conversation $conversation;

    public string $input = '';

    public function mount(Conversation $conversation): void
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $this->conversation = $conversation;
    }

    public function sendMessage(OpenRouterService $openRouter, CardParserService $parser): void
    {
        if (trim($this->input) === '' || $this->conversation->isCompleted()) {
            return;
        }

        $text = trim($this->input);
        $this->input = '';

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => $text,
        ]);

        if ($this->conversation->title === 'Nova conversa') {
            $this->conversation->update([
                'title' => Str::limit($text, 60),
            ]);
        }

        try {
            $this->conversation->refresh();
            $this->conversation->load('messages');

            $assistantResponse = $openRouter->chat($this->conversation);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $assistantResponse,
            ]);

            if ($parser->hasCard($assistantResponse)) {
                $this->persistCard($parser, $assistantResponse);
            }
        } catch (Throwable $e) {
            Log::error('Falha ao consultar a OpenRouter', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => '⚠️ Erro OpenRouter: '.$e->getMessage(),
            ]);
        }

        $this->conversation->refresh();
        $this->conversation->load(['messages', 'card']);

        if ($this->conversation->card !== null) {
            $this->redirect(route('conversations.show', $this->conversation));
        }
    }

    private function persistCard(CardParserService $parser, string $response): void
    {
        try {
            $cardData = $parser->parse($response);

            Card::updateOrCreate(
                ['conversation_id' => $this->conversation->id],
                array_merge($cardData, [
                    'user_id' => $this->conversation->user_id,
                    'status' => 'draft',
                ])
            );

            $this->conversation->update(['status' => 'completed']);
        } catch (Throwable $e) {
            Log::error('Card parsing failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.conversation-chat', [
            'messages' => $this->conversation->messages,
            'card' => $this->conversation->card,
        ]);
    }
}
