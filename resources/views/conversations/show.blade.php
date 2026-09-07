<x-app-layout>
    <x-slot name="title">{{ $conversation->title }} — PM Helper</x-slot>

    <div class="h-full min-h-0 flex overflow-hidden">

        {{-- Painel principal: Chat --}}
        <div class="flex-1 overflow-hidden flex flex-col {{ $conversation->card ? 'w-1/2' : 'w-full' }}">

            {{-- Header da conversa --}}
            <div class="bg-white border-b border-gray-200 px-5 py-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('conversations.index') }}"
                       class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h2 class="font-semibold text-gray-800 truncate text-sm">{{ $conversation->title }}</h2>
                    @if ($conversation->isCompleted())
                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700 shrink-0">✓ Card gerado</span>
                    @elseif ($conversation->isScopeTooBroad())
                        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 shrink-0">Escopo amplo demais</span>
                    @else
                        <span
                            x-data="{ complete: @js($conversation->isInterviewComplete()), scopeTooBroad: false }"
                            x-on:interview-ready.window="if (!scopeTooBroad) complete = true"
                            x-on:interview-scope-too-broad.window="scopeTooBroad = true; complete = false"
                            class="shrink-0"
                        >
                            <span
                                x-show="!scopeTooBroad"
                                class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-600"
                                x-text="complete ? 'Pronto para gerar o card' : 'Interview em andamento'"
                            >
                                {{ $conversation->isInterviewComplete() ? 'Pronto para gerar o card' : 'Interview em andamento' }}
                            </span>
                            <span
                                x-show="scopeTooBroad"
                                x-cloak
                                class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800"
                            >
                                Escopo amplo demais
                            </span>
                        </span>
                    @endif
                </div>
                <form method="POST" action="{{ route('conversations.destroy', $conversation) }}"
                      onsubmit="return confirm('Excluir esta conversa?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-gray-400 hover:text-red-500 transition-colors">
                        Excluir
                    </button>
                </form>
            </div>

            {{-- Componente Livewire do chat --}}
            @livewire('conversation-chat', ['conversation' => $conversation])
        </div>

        {{-- Painel do card (aparece quando card foi gerado) --}}
        @if ($conversation->card)
            <div class="w-1/2 border-l border-gray-200 overflow-y-auto bg-gray-50">
                @livewire('card-preview', ['card' => $conversation->card])
            </div>
        @endif

    </div>
</x-app-layout>
