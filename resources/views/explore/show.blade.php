<x-app-layout>
    <x-slot name="title">{{ $conversation->title }} — PM Helper</x-slot>

    <div class="h-full min-h-0 flex overflow-hidden">
        <div class="flex-1 overflow-hidden flex flex-col w-full">

            <div class="bg-white border-b border-gray-200 px-5 py-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('explorar.index') }}"
                       class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <h2 class="font-semibold text-gray-800 truncate text-sm">{{ $conversation->title }}</h2>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-600 shrink-0">Exploração</span>
                </div>
                <form method="POST" action="{{ route('explorar.destroy', $conversation) }}"
                      onsubmit="return confirm('Excluir esta exploração?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-gray-400 hover:text-red-500 transition-colors">
                        Excluir
                    </button>
                </form>
            </div>

            @livewire('explore-chat', ['conversation' => $conversation])
        </div>
    </div>
</x-app-layout>
