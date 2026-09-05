<div class="flex-1 flex flex-col overflow-hidden h-full">

    {{-- Mensagens --}}
    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4" id="messages-container">

        @if ($messages->isEmpty())
            <div class="flex flex-col items-center justify-center h-full text-center py-12">
                <div class="w-14 h-14 bg-indigo-50 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-700 mb-1">Assistente de Discovery</h3>
                <p class="text-sm text-gray-400 max-w-xs">
                    Descreva uma necessidade ou ideia. O assistente vai conduzir um processo de discovery e gerar um card estruturado.
                </p>
            </div>
        @else
            @foreach ($messages as $message)
                <div class="flex {{ $message->isFromUser() ? 'justify-end' : 'justify-start' }}">
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
                            $content = $message->content;
                            // Remove bloco CARD_JSON da exibição
                            $content = preg_replace('/<CARD_JSON>.*?<\/CARD_JSON>/s', '', $content);
                            $content = trim($content);
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
        <div wire:loading.flex wire:target="sendMessage" class="justify-start">
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

    {{-- Input --}}
    <div class="bg-white border-t border-gray-200 px-4 py-3 shrink-0">
        @if ($conversation->isCompleted())
            <div class="text-center text-sm text-gray-400 py-2">
                ✓ Conversa concluída — o card foi gerado. <a href="{{ route('conversations.store') }}" class="text-indigo-500 hover:underline" onclick="event.preventDefault(); document.getElementById('new-conv-form').submit()">Iniciar nova conversa</a>
            </div>
            <form id="new-conv-form" method="POST" action="{{ route('conversations.store') }}" class="hidden">@csrf</form>
        @else
            <form wire:submit.prevent="sendMessage" class="flex gap-2">
                <textarea
                    wire:model="input"
                    wire:keydown.enter.prevent="sendMessage"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    placeholder="Descreva sua necessidade ou ideia..."
                    rows="1"
                    class="flex-1 resize-none rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-800
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent
                           placeholder-gray-400 transition-colors"
                ></textarea>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors
                           disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        @endif
    </div>
</div>

<script>
    // Scroll para a última mensagem automaticamente
    document.addEventListener('livewire:updated', () => {
        const container = document.getElementById('messages-container');
        if (container) container.scrollTop = container.scrollHeight;
    });
    window.addEventListener('load', () => {
        const container = document.getElementById('messages-container');
        if (container) container.scrollTop = container.scrollHeight;
    });
</script>
