<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CardController extends Controller
{
    public function index(): View
    {
        $cards = Auth::user()
            ->cards()
            ->with('conversation')
            ->latest()
            ->paginate(20);

        return view('cards.index', compact('cards'));
    }

    public function show(Card $card): View
    {
        abort_if($card->user_id !== Auth::id(), 403);

        $card->load('conversation');

        return view('cards.show', compact('card'));
    }
}
