<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardDiscovery;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardDiscovery>
 */
class CardDiscoveryFactory extends Factory
{
    protected $model = CardDiscovery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Conversa',
            'status' => 'completed',
        ]);
        $card = Card::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Card',
            'priority' => 'medium',
            'status' => 'approved',
        ]);

        return [
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_REQUESTED,
            'deadline_at' => now()->addMinutes(20),
        ];
    }
}
