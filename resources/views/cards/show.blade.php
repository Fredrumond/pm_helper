<x-app-layout>
    <x-slot name="title">{{ $card->title }} — PM Helper</x-slot>

    <div class="max-w-3xl mx-auto px-4 py-8">

        <div class="flex items-center gap-2 mb-5 text-sm text-gray-500">
            <a href="{{ route('cards.index') }}" class="hover:text-indigo-600">Cards</a>
            <span>/</span>
            <span class="text-gray-800 truncate">{{ $card->title }}</span>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div class="px-6 py-5 border-b border-gray-100">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full
                        {{ match($card->type) {
                            'feature'   => 'bg-blue-100 text-blue-700',
                            'bug'       => 'bg-red-100 text-red-700',
                            'tech_debt' => 'bg-orange-100 text-orange-700',
                            'spike'     => 'bg-purple-100 text-purple-700',
                            default     => 'bg-gray-100 text-gray-600',
                        } }}">{{ $card->typeLabel() }}</span>

                    <span class="text-xs font-medium px-2.5 py-1 rounded-full
                        {{ match($card->priority) {
                            'low'      => 'bg-gray-100 text-gray-500',
                            'medium'   => 'bg-yellow-100 text-yellow-700',
                            'high'     => 'bg-orange-100 text-orange-700',
                            'critical' => 'bg-red-100 text-red-700',
                            default    => 'bg-gray-100 text-gray-500',
                        } }}">Prioridade {{ $card->priorityLabel() }}</span>

                    @if ($card->estimated_complexity)
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600">
                            Complexidade {{ $card->estimated_complexity }}
                        </span>
                    @endif

                    <span class="ml-auto text-xs font-medium px-2.5 py-1 rounded-full
                        {{ $card->isApproved() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ $card->isApproved() ? '✓ Aprovado' : 'Rascunho' }}
                    </span>
                </div>
                <h1 class="text-xl font-bold text-gray-900">{{ $card->title }}</h1>
            </div>

            <div class="px-6 py-5 space-y-6">

                {{-- User Story --}}
                <section>
                    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">História do Usuário</h2>
                    <p class="text-gray-800 italic text-base">"{{ $card->user_story }}"</p>
                </section>

                {{-- Contexto --}}
                @if ($card->context)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Contexto</h2>
                        <p class="text-gray-700 leading-relaxed">{{ $card->context }}</p>
                    </section>
                @endif

                {{-- Critérios de Aceite --}}
                @if ($card->acceptance_criteria)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Critérios de Aceite</h2>
                        <ul class="space-y-2">
                            @foreach ($card->acceptance_criteria as $i => $criterion)
                                <li class="flex gap-3">
                                    <span class="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-700 text-xs flex items-center justify-center font-bold mt-0.5">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="text-gray-700 text-sm leading-relaxed">{{ $criterion }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Fora do Escopo --}}
                @if ($card->out_of_scope && count($card->out_of_scope) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Fora do Escopo</h2>
                        <ul class="space-y-2">
                            @foreach ($card->out_of_scope as $item)
                                <li class="flex gap-2 text-sm text-gray-500">
                                    <span class="text-red-400 shrink-0">✕</span>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Notas Técnicas --}}
                @if ($card->technical_notes)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Notas Técnicas</h2>
                        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 font-mono leading-relaxed border border-gray-200">
                            {{ $card->technical_notes }}
                        </div>
                    </section>
                @endif

                {{-- Labels --}}
                @if ($card->labels && count($card->labels) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Labels</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($card->labels as $label)
                                <span class="px-3 py-1 text-xs bg-gray-100 text-gray-600 rounded-full border border-gray-200">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif

                <x-session-usage :conversation="$card->conversation" />

            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="{{ route('conversations.show', $card->conversation) }}"
                   class="text-sm text-indigo-600 hover:underline">
                    ← Ver conversa de origem
                </a>
                <span class="text-xs text-gray-400">Gerado em {{ $card->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

    </div>
</x-app-layout>
