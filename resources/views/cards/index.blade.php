<x-app-layout>
    <x-slot name="title">Cards Gerados — PM Helper</x-slot>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Cards Gerados</h1>
            <p class="text-sm text-gray-500 mt-1">Todos os cards criados através de conversas de discovery.</p>
        </div>

        @if ($cards->isEmpty())
            <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-gray-500 text-sm">Nenhum card gerado ainda.</p>
                <a href="{{ route('conversations.index') }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">
                    Iniciar uma conversa →
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($cards as $card)
                    <a href="{{ route('cards.show', $card) }}"
                       class="block bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition-all group">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs px-1.5 py-0.5 rounded font-medium
                                        {{ match($card->priority) {
                                            'low'      => 'bg-gray-50 text-gray-500',
                                            'medium'   => 'bg-yellow-50 text-yellow-700',
                                            'high'     => 'bg-orange-50 text-orange-700',
                                            'critical' => 'bg-red-50 text-red-700',
                                            default    => 'bg-gray-50 text-gray-500',
                                        } }}">{{ $card->priorityLabel() }}</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded font-medium
                                        {{ $card->isApproved() ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                                        {{ $card->isApproved() ? '✓ Aprovado' : 'Rascunho' }}
                                    </span>
                                </div>
                                <p class="font-semibold text-gray-900 group-hover:text-indigo-600 truncate">{{ $card->title }}</p>
                                @if ($card->objetivo)
                                    <p class="text-xs text-gray-400 mt-1 truncate">{{ $card->objetivo }}</p>
                                @endif
                            </div>
                            <div class="ml-4 text-xs text-gray-400 shrink-0 text-right">
                                <p>{{ $card->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $cards->links() }}</div>
        @endif
    </div>
</x-app-layout>
