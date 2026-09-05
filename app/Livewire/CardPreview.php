<?php

namespace App\Livewire;

use App\Models\Card;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class CardPreview extends Component
{
    public Card $card;

    public function mount(Card $card): void
    {
        abort_if($card->user_id !== Auth::id(), 403);
        $this->card = $card;
    }

    public function approve(): void
    {
        abort_if($this->card->user_id !== Auth::id(), 403);
        $this->card->update(['status' => 'approved']);
        $this->card->refresh();
        session()->flash('message', 'Card aprovado com sucesso!');
    }

    public function render()
    {
        return view('livewire.card-preview');
    }
}
