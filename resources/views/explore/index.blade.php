<x-app-layout>
    <x-slot name="title">Explorar — PM Helper</x-slot>

    <div class="max-w-4xl mx-auto px-4 py-8">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Suas Explorações</h1>
                <p class="text-sm text-gray-500 mt-1">Chat livre para perguntar e investigar ideias, sem gerar card.</p>
            </div>
            <form method="POST" action="{{ route('explorar.store') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nova Exploração
                </button>
            </form>
        </div>

        @if (session('message'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
                {{ session('message') }}
            </div>
        @endif

        @if ($explorations->isEmpty())
            <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-500 text-sm">Nenhuma exploração ainda.</p>
                <p class="text-gray-400 text-xs mt-1">Clique em "Nova Exploração" para começar.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($explorations as $exploration)
                    <a href="{{ route('explorar.show', $exploration) }}"
                       class="block bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition-all group">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate group-hover:text-indigo-600">
                                    {{ $exploration->title }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $exploration->created_at->diffForHumans() }}
                                    &middot;
                                    {{ $exploration->messages_count }} mensagens
                                </p>
                            </div>
                            <div class="flex items-center gap-3 ml-4 shrink-0">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-100 text-indigo-600">
                                    Exploração
                                </span>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $explorations->links() }}
            </div>
        @endif

    </div>
</x-app-layout>
