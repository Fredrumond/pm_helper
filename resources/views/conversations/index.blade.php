<x-app-layout>
    <x-slot name="title">Conversas — PM Helper</x-slot>

    <div class="max-w-4xl mx-auto px-4 py-8">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Suas Conversas</h1>
                <p class="text-sm text-gray-500 mt-1">Cada conversa conduz um processo de discovery e gera um card.</p>
            </div>
            <form method="POST" action="{{ route('conversations.store') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nova Conversa
                </button>
            </form>
        </div>

        @if ($conversations->isEmpty())
            <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-500 text-sm">Nenhuma conversa ainda.</p>
                <p class="text-gray-400 text-xs mt-1">Clique em "Nova Conversa" para começar.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($conversations as $conversation)
                    <a href="{{ route('conversations.show', $conversation) }}"
                       class="block bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition-all group">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate group-hover:text-indigo-600">
                                    {{ $conversation->title }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $conversation->created_at->diffForHumans() }}
                                    &middot;
                                    {{ $conversation->messages_count ?? $conversation->messages->count() }} mensagens
                                </p>
                            </div>
                            <div class="flex items-center gap-3 ml-4 shrink-0">
                                @if ($conversation->card)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full
                                        {{ $conversation->card->isApproved() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $conversation->card->isApproved() ? '✓ Aprovado' : '⟳ Rascunho' }}
                                    </span>
                                @elseif ($conversation->isCompleted())
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-500">
                                        Concluída
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-100 text-indigo-600">
                                        Em andamento
                                    </span>
                                @endif
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $conversations->links() }}
            </div>
        @endif

    </div>
</x-app-layout>
