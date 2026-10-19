<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(): View
    {
        $user = $this->authenticatedUser();

        $explorations = $user->conversations()
            ->explore()
            ->withCount('messages')
            ->latest()
            ->paginate(20);

        return view('explore.index', compact('explorations'));
    }

    public function store(): RedirectResponse
    {
        $conversation = Conversation::createExploreFor($this->authenticatedUser());

        return redirect()->route('explorar.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        abort_unless($conversation->isExplore(), 404);

        $conversation->load('messages');

        return view('explore.show', compact('conversation'));
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        abort_unless($conversation->isExplore(), 404);

        $conversation->delete();

        return redirect()->route('explorar.index')
            ->with('message', 'Exploração excluída.');
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
