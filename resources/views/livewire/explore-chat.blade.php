<div class="flex-1 flex flex-col overflow-hidden h-full min-h-0">

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
                    <h3 class="font-semibold text-gray-800 mb-1">Assistente de Exploração</h3>
                    <p class="text-sm text-gray-500 max-w-md mx-auto">
                        Pergunte qualquer coisa. O assistente responde direto, sem entrevista e sem gerar card.
                    </p>
                </div>
            </div>
        @else
            @foreach ($messages as $message)
                <div wire:key="explore-message-{{ $message->id }}" class="flex {{ $message->isFromUser() ? 'justify-end' : 'justify-start' }}">
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
                        {!! nl2br(e($message->content)) !!}
                    </div>
                </div>
            @endforeach
        @endif

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
    </div>

    <div class="bg-white border-t border-gray-200 px-4 py-3 shrink-0">
        <form
            wire:submit="sendMessage"
            x-data="chatComposer()"
            class="relative max-w-3xl mx-auto"
        >
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm transition-colors focus-within:border-indigo-300 focus-within:ring-2 focus-within:ring-indigo-200">
                <textarea
                    wire:model="input"
                    wire:keydown.enter.exact.prevent="sendMessage"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    x-ref="input"
                    @input="onDraftInput($event)"
                    placeholder="O que você quer explorar?"
                    rows="2"
                    class="w-full resize-none bg-transparent px-4 pt-3 pb-1.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none disabled:opacity-60"
                ></textarea>

                <div class="flex items-center justify-end gap-2 px-2.5 pb-2.5">
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
        </form>
    </div>
</div>
