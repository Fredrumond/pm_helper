<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(): View
    {
        $conversations = Auth::user()
            ->conversations()
            ->with('card')
            ->latest()
            ->paginate(20);

        return view('conversations.index', compact('conversations'));
    }

    public function store(): RedirectResponse
    {
        $conversation = Auth::user()->conversations()->create([
            'title'  => 'Nova conversa',
            'status' => 'in_progress',
        ]);

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        abort_if($conversation->user_id !== Auth::id(), 403);

        $conversation->load(['messages', 'card']);

        return view('conversations.show', compact('conversation'));
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        abort_if($conversation->user_id !== Auth::id(), 403);

        $conversation->delete();

        return redirect()->route('conversations.index')
            ->with('message', 'Conversa excluída.');
    }
}
