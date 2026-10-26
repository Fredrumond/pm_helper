<section @if($discovery?->isInProgress()) wire:poll.5s @endif>
    @if ($visible)
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Anexos</h2>

        <div class="space-y-3">
            @if ($discovery)
                <span class="inline-flex text-xs font-medium px-2.5 py-1 rounded-full
                    {{ match($discovery->status) {
                        'completed' => 'bg-green-100 text-green-700',
                        'failed' => 'bg-red-100 text-red-700',
                        default => 'bg-amber-100 text-amber-800',
                    } }}">{{ $discovery->statusLabel() }}</span>
            @else
                <p class="text-sm text-gray-600">Nenhum Discovery gerado para este card.</p>
            @endif

            @if ($discovery?->isInProgress())
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Discovery solicitado. Você pode sair desta tela; o resultado aparece aqui quando a geração terminar.
                </p>
                @if ($hasDocument)
                    <p class="text-sm text-gray-600">O Discovery anterior continua disponível até a nova geração concluir com sucesso.</p>
                @endif
            @endif

            @if ($discovery?->isFailed())
                <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                    {{ $discovery->error_message }}
                </p>
                @if ($hasDocument)
                    <p class="text-sm text-gray-600">O Discovery anterior continua disponível.</p>
                @endif
            @endif

            @error('discovery')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($canGenerate && Auth::id() === $card->user_id)
                <button type="button"
                        wire:click="generate"
                        wire:loading.attr="disabled"
                        wire:target="generate"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="generate">{{ $buttonLabel }}</span>
                    <span wire:loading wire:target="generate">Solicitando…</span>
                </button>
            @endif

            @if ($hasDocument)
                <div class="border border-gray-200 rounded-lg px-3 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-gray-800">{{ $attachmentTitle }}</span>
                        <button type="button"
                                wire:click="toggleDocument"
                                class="text-sm text-indigo-600 hover:underline">
                            {{ $documentOpen ? 'Fechar' : 'Abrir' }}
                        </button>
                    </div>
                    @if ($documentHtml)
                        <div class="discovery-markdown mt-3 text-sm text-gray-800">{!! $documentHtml !!}</div>
                        @if (count($questions) > 0 && Auth::id() === $card->user_id)
                            <div class="mt-4 space-y-3 border-t border-gray-200 pt-3">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Dúvidas</p>
                                @foreach ($questions as $index => $question)
                                    <label class="block" wire:key="discovery-answer-{{ $index }}">
                                        <span class="block text-sm font-medium text-gray-800 mb-1">{{ $question }}</span>
                                        <textarea
                                            data-discovery-answer
                                            wire:model.live="answers.{{ $index }}"
                                            rows="2"
                                            @disabled($answersLocked)
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200 {{ $answersLocked ? 'bg-gray-50 text-gray-500' : '' }}"
                                        >{{ $answers[$index] ?? '' }}</textarea>
                                    </label>
                                @endforeach
                                @unless ($answersLocked)
                                    <button type="button"
                                            wire:click="regenerateWithAnswers"
                                            wire:loading.attr="disabled"
                                            wire:target="regenerateWithAnswers"
                                            data-regenerate-ready="{{ $canRegenerateWithAnswers ? '1' : '0' }}"
                                            @disabled(! $canRegenerateWithAnswers)
                                            title="{{ $canRegenerateWithAnswers ? '' : 'Preencha ao menos uma resposta' }}"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span wire:loading.remove wire:target="regenerateWithAnswers">Gerar de novo com respostas</span>
                                        <span wire:loading wire:target="regenerateWithAnswers">Solicitando…</span>
                                    </button>
                                @endunless
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <style>
            .discovery-markdown h1 { font-size: 1.25rem; font-weight: 700; margin: 1rem 0 .5rem; }
            .discovery-markdown h2 { font-size: 1.05rem; font-weight: 650; margin: 1rem 0 .4rem; }
            .discovery-markdown h3 { font-size: 1rem; font-weight: 650; margin: .8rem 0 .35rem; }
            .discovery-markdown p { margin: .5rem 0; line-height: 1.6; }
            .discovery-markdown ul, .discovery-markdown ol { margin: .5rem 0 .5rem 1.25rem; }
            .discovery-markdown ul { list-style: disc; }
            .discovery-markdown ol { list-style: decimal; }
            .discovery-markdown code { font-size: .85em; background: #f3f4f6; padding: .1rem .3rem; border-radius: .25rem; }
            .discovery-markdown pre { background: #f9fafb; border: 1px solid #e5e7eb; padding: .75rem; overflow-x: auto; border-radius: .5rem; }
            .discovery-markdown pre code { background: transparent; padding: 0; }
            .discovery-markdown pre.mermaid { background: #fff; }
            .discovery-markdown pre.mermaid svg { display: block; max-width: 100%; height: auto; margin: 0 auto; }
            .discovery-markdown a { color: #4f46e5; text-decoration: underline; }
            .discovery-markdown blockquote { border-left: 3px solid #e5e7eb; padding-left: .75rem; color: #4b5563; }
            .discovery-markdown table { width: 100%; border-collapse: collapse; margin: .75rem 0; }
            .discovery-markdown th, .discovery-markdown td { border: 1px solid #e5e7eb; padding: .4rem .6rem; text-align: left; }
        </style>
    @endif
</section>
