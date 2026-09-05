<div class="flex-1 flex flex-col overflow-hidden h-full min-h-0">

    {{-- Mensagens --}}
    <div class="flex-1 overflow-y-auto px-5 py-4" id="messages-container">
        <div @class(['max-w-3xl mx-auto', 'h-full' => $messages->isEmpty(), 'space-y-4' => $messages->isNotEmpty()])>

        @if ($messages->isEmpty())
            <div class="flex items-center justify-center h-full">
                <div class="w-full text-center py-16 px-6 bg-white rounded-xl border border-gray-200">
                    <div class="w-14 h-14 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Assistente de Interview</h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">
                        Descreva uma necessidade ou ideia. O assistente conduz a entrevista e, quando estiver pronto, você gera o card.
                    </p>
                </div>
            </div>
        @else
            @foreach ($messages as $message)
                <div wire:key="message-{{ $message->id }}" class="flex {{ $message->isFromUser() ? 'justify-end' : 'justify-start' }}">
                    @if ($message->isFromAssistant())
                        <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center shrink-0 mr-2 mt-1">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                    @endif

                    <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed
                        {{ $message->isFromUser()
                            ? 'bg-indigo-600 text-white rounded-br-sm'
                            : 'bg-white border border-gray-200 text-gray-800 rounded-bl-sm shadow-sm' }}">
                        @php
                            $content = app(\App\Services\CardParserService::class)->extractTextOnly($message->content);
                        @endphp
                        {!! nl2br(e($content)) !!}

                        @if ($card && $message->isFromAssistant() && str_contains($message->content, '<CARD_JSON>'))
                            <div class="mt-2 pt-2 border-t border-gray-100">
                                <span class="inline-flex items-center gap-1 text-xs text-indigo-600 font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                                    </svg>
                                    Card gerado → veja no painel ao lado
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Indicador de digitação enquanto a OpenRouter responde --}}
        <div wire:loading.flex wire:target="sendMessage,generateCard" class="justify-start">
            <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center shrink-0 mr-2 mt-1">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                <div class="flex gap-1 items-center h-4">
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        </div>
        </div>
    </div>

    {{-- Composer --}}
    <div class="bg-white border-t border-gray-200 px-4 py-3 shrink-0">
        @if ($conversation->isCompleted())
            <div class="text-center text-sm text-gray-400 py-2">
                ✓ Conversa concluída — o card foi gerado. <a href="{{ route('conversations.store') }}" class="text-indigo-500 hover:underline" onclick="event.preventDefault(); document.getElementById('new-conv-form').submit()">Iniciar nova conversa</a>
            </div>
            <form id="new-conv-form" method="POST" action="{{ route('conversations.store') }}" class="hidden">@csrf</form>
        @else
            @if ($showGenerateCardButton)
                <div class="max-w-3xl mx-auto mb-3" wire:key="generate-card-cta" x-data>
                    <button
                        type="button"
                        @click.prevent="$wire.generateCard()"
                        wire:loading.attr="disabled"
                        wire:target="generateCard,sendMessage"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg wire:loading.remove wire:target="generateCard" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                        </svg>
                        <svg wire:loading wire:target="generateCard" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="generateCard">Gerar Card</span>
                        <span wire:loading wire:target="generateCard">Gerando card...</span>
                    </button>
                    <p class="mt-1.5 text-center text-[11px] text-gray-400">
                        A entrevista está pronta. Você ainda pode complementar o contexto antes de gerar.
                    </p>
                </div>
            @endif

            <form
                wire:submit="sendMessage"
                x-data="chatComposer(@js($models))"
                @click.outside="showModels = false"
                class="relative max-w-3xl mx-auto"
            >
                {{-- Modelos --}}
                <div
                    x-show="showModels"
                    x-cloak
                    x-transition.opacity
                    class="absolute bottom-12 left-0 z-20 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg"
                    role="listbox"
                    aria-label="Modelos"
                >
                    <div class="border-b border-gray-100 p-2">
                        <input
                            type="search"
                            x-model="modelQuery"
                            @keydown.enter.prevent
                            placeholder="Buscar modelos"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                    </div>
                    <ul class="max-h-56 overflow-y-auto py-1">
                        <template x-for="model in filteredModels" :key="model.id">
                            <li>
                                <button
                                    type="button"
                                    @click="chooseModel(model.id)"
                                    class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-indigo-50"
                                >
                                    <span>
                                        <span class="block text-sm text-gray-800" x-text="model.name"></span>
                                        <span class="block text-[11px] text-gray-400" x-text="model.tier"></span>
                                    </span>
                                    <svg x-show="model.id === $wire.selectedModel" class="w-4 h-4 shrink-0 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm transition-colors focus-within:border-indigo-300 focus-within:ring-2 focus-within:ring-indigo-200">
                    <textarea
                        wire:model="input"
                        wire:keydown.enter.exact.prevent="sendMessage"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        x-ref="input"
                        @input="onDraftInput($event)"
                        @keydown.escape="showModels = false"
                        placeholder="Descreva sua necessidade ou ideia..."
                        rows="2"
                        class="w-full resize-none bg-transparent px-4 pt-3 pb-1.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none disabled:opacity-60"
                    ></textarea>

                    <div class="flex items-center justify-between gap-2 px-2.5 pb-2.5">
                        <div class="flex items-center min-w-0">
                            <button
                                type="button"
                                @click="toggleModels()"
                                class="inline-flex items-center gap-1 max-w-full rounded-lg px-2 py-1 text-xs text-gray-600 transition-colors hover:bg-gray-100"
                                :aria-expanded="showModels.toString()"
                            >
                                <span class="truncate">{{ $selectedModelMeta['name'] }}</span>
                                @if ($selectedModelMeta['tier'] !== '')
                                    <span class="hidden text-gray-400 sm:inline">{{ $selectedModelMeta['tier'] }}</span>
                                @endif
                                <svg class="w-3 h-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center gap-1">
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="soon('skills')"
                                    title="Skills (em breve)"
                                    class="rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800"
                                >
                                    <span class="block text-xs font-mono leading-none px-0.5">/</span>
                                </button>
                                <span
                                    x-show="soonHint === 'skills'"
                                    x-cloak
                                    x-transition.opacity
                                    class="absolute bottom-full right-0 mb-2 whitespace-nowrap rounded-md bg-gray-800 px-2 py-1 text-[11px] text-white"
                                >
                                    Em breve
                                </span>
                            </div>

                            <div class="relative">
                                <button
                                    type="button"
                                    @click="soon('attach')"
                                    title="Anexar arquivo (em breve)"
                                    class="rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M21.44 11.05l-8.49 8.49a5.25 5.25 0 01-7.42-7.42l8.48-8.49a3.5 3.5 0 014.95 4.95l-8.48 8.49a1.75 1.75 0 11-2.47-2.47l7.78-7.78"/>
                                    </svg>
                                </button>
                                <span
                                    x-show="soonHint === 'attach'"
                                    x-cloak
                                    x-transition.opacity
                                    class="absolute bottom-full right-0 mb-2 whitespace-nowrap rounded-md bg-gray-800 px-2 py-1 text-[11px] text-white"
                                >
                                    Em breve
                                </span>
                            </div>

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="sendMessage"
                                title="Enviar (Enter)"
                                class="rounded-lg bg-indigo-600 p-1.5 text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
