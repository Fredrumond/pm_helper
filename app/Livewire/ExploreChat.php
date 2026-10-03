<?php

namespace App\Livewire;

use App\Contracts\LlmGateway;
use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Models\Conversation;
use App\Models\Message;
use App\Prompts\SystemPromptCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class ExploreChat extends Component
{
    public const MODEL = 'gpt-4o-mini';

    public Conversation $conversation;

    public string $input = '';

    public function mount(Conversation $conversation): void
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        abort_unless($conversation->isExplore(), 404);

        $this->conversation = $conversation;
    }

    public function sendMessage(LlmGateway $llm, SystemPromptCatalog $prompts): void
    {
        abort_if($this->conversation->user_id !== Auth::id(), 403);

        if (trim($this->input) === '') {
            return;
        }

        $text = trim($this->input);
        $this->input = '';

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => $text,
        ]);

        if ($this->conversation->title === Conversation::EXPLORE_TITLE) {
            $this->conversation->update([
                'title' => Str::limit($text, 60),
            ]);
        }

        try {
            $this->conversation->refresh();
            $this->conversation->load('messages');

            $prompt = $prompts->current('explore');
            $assistantResponse = $llm->completePrompt(
                array_merge(
                    [['role' => 'system', 'content' => $prompt->content]],
                    $this->conversation->toLlmHistory(),
                ),
                self::MODEL,
                'explore',
                $this->conversation,
            );

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $assistantResponse,
            ]);
        } catch (LlmTemporarilyUnavailableException $e) {
            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao consultar a LLM no explorar', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => '⚠️ Erro LLM: '.$e->getMessage(),
            ]);
        }

        $this->conversation->refresh();
        $this->conversation->load('messages');
    }

    public function render()
    {
        $this->conversation->load('messages');

        return view('livewire.explore-chat', [
            'messages' => $this->conversation->messages,
        ]);
    }
}
