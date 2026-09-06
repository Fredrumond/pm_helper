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
                        {{ match($card->priority) {
                            'low'      => 'bg-gray-100 text-gray-500',
                            'medium'   => 'bg-yellow-100 text-yellow-700',
                            'high'     => 'bg-orange-100 text-orange-700',
                            'critical' => 'bg-red-100 text-red-700',
                            default    => 'bg-gray-100 text-gray-500',
                        } }}">Prioridade {{ $card->priorityLabel() }}</span>

                    <span class="ml-auto text-xs font-medium px-2.5 py-1 rounded-full
                        {{ $card->isApproved() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ $card->isApproved() ? '✓ Aprovado' : 'Rascunho' }}
                    </span>
                </div>
                <h1 class="text-xl font-bold text-gray-900">{{ $card->title }}</h1>
            </div>

            <div class="px-6 py-5 space-y-6">

                @if ($card->objetivo)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Objetivo</h2>
                        <p class="text-gray-700 leading-relaxed">{{ $card->objetivo }}</p>
                    </section>
                @endif

                @if ($card->como_funciona_hoje)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Como funciona hoje</h2>
                        <p class="text-gray-700 leading-relaxed">{{ $card->como_funciona_hoje }}</p>
                    </section>
                @endif

                @if ($card->regras && count($card->regras) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Regras</h2>
                        <ul class="space-y-2">
                            @foreach ($card->regras as $i => $regra)
                                <li class="flex gap-3">
                                    <span class="shrink-0 w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center font-bold mt-0.5">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="text-gray-700 text-sm leading-relaxed">{{ $regra }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($card->onde && count($card->onde) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Onde</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($card->onde as $canal)
                                <span class="px-3 py-1 text-xs bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100">
                                    {{ $canal }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($card->aceite && count($card->aceite) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Aceite</h2>
                        <ul class="space-y-2">
                            @foreach ($card->aceite as $i => $criterio)
                                <li class="flex gap-3">
                                    <span class="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-700 text-xs flex items-center justify-center font-bold mt-0.5">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="text-gray-700 text-sm leading-relaxed">{{ $criterio }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($card->o_que_nao_fazer && count($card->o_que_nao_fazer) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">O que não fazer</h2>
                        <ul class="space-y-2">
                            @foreach ($card->o_que_nao_fazer as $item)
                                <li class="flex gap-2 text-sm text-gray-500">
                                    <span class="text-red-400 shrink-0">✕</span>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($card->stakeholders && count($card->stakeholders) > 0)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Stakeholders</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($card->stakeholders as $pessoa)
                                <span class="px-3 py-1 text-xs bg-gray-100 text-gray-600 rounded-full border border-gray-200">
                                    {{ $pessoa }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($card->como_validar)
                    <section>
                        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Como validar</h2>
                        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 leading-relaxed border border-gray-200 whitespace-pre-line">
                            {{ $card->como_validar }}
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
