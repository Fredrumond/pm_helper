<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(): View
    {
        $conversations = Auth::user()?->can('admin')
            ? Conversation::query()
                ->interview()
                ->with(['user', 'card'])
                ->latest()
                ->paginate(20)
            : Auth::user()
                ->conversations()
                ->interview()
                ->with('card')
                ->latest()
                ->paginate(20);

        return view('conversations.index', compact('conversations'));
    }

    public function store(): RedirectResponse
    {
        $existing = Auth::user()
            ->conversations()
            ->interview()
            ->doesntHave('messages')
            ->latest('id')
            ->first();

        if ($existing) {
            return redirect()->route('conversations.show', $existing);
        }

        $conversation = Auth::user()->conversations()->create([
            'title' => 'Nova conversa',
            'status' => 'in_progress',
        ]);

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        abort_unless(
            $conversation->user_id === Auth::id() || Auth::user()?->can('admin'),
            403,
        );
        abort_if($conversation->isExplore(), 404);

        $conversation->load(['messages', 'card', 'llmUsages']);

        return view('conversations.show', compact('conversation'));
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        abort_if($conversation->isExplore(), 404);

        $conversation->delete();

        return redirect()->route('conversations.index')
            ->with('message', 'Conversa excluída.');
    }
}
