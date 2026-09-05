<div class="px-5 py-5">

    @if (session('message'))
        <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header do card --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full
                    {{ match($card->type) {
                        'feature'   => 'bg-blue-100 text-blue-700',
                        'bug'       => 'bg-red-100 text-red-700',
                        'tech_debt' => 'bg-orange-100 text-orange-700',
                        'spike'     => 'bg-purple-100 text-purple-700',
                        default     => 'bg-gray-100 text-gray-600',
                    } }}">
                    {{ $card->typeLabel() }}
                </span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full
                    {{ match($card->priority) {
                        'low'      => 'bg-gray-100 text-gray-500',
                        'medium'   => 'bg-yellow-100 text-yellow-700',
                        'high'     => 'bg-orange-100 text-orange-700',
                        'critical' => 'bg-red-100 text-red-700',
                        default    => 'bg-gray-100 text-gray-500',
                    } }}">
                    ↑ {{ $card->priorityLabel() }}
                </span>
                @if ($card->estimated_complexity)
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">
                        {{ $card->estimated_complexity }}
                    </span>
                @endif
            </div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">{{ $card->title }}</h2>
        </div>
        <span class="shrink-0 px-2 py-0.5 text-xs rounded-full
            {{ $card->isApproved() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
            {{ $card->isApproved() ? '✓ Aprovado' : 'Rascunho' }}
        </span>
    </div>

    {{-- User Story --}}
    <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">História do Usuário</h3>
        <p class="text-sm text-gray-800 italic">"{{ $card->user_story }}"</p>
    </div>

    {{-- Contexto --}}
    @if ($card->context)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Contexto</h3>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $card->context }}</p>
        </div>
    @endif

    {{-- Critérios de Aceite --}}
    @if ($card->acceptance_criteria)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Critérios de Aceite</h3>
            <ul class="space-y-2">
                @foreach ($card->acceptance_criteria as $criterion)
                    <li class="flex gap-2 text-sm text-gray-700">
                        <span class="text-green-500 shrink-0 mt-0.5">✓</span>
                        <span>{{ $criterion }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Fora do Escopo --}}
    @if ($card->out_of_scope && count($card->out_of_scope) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Fora do Escopo</h3>
            <ul class="space-y-2">
                @foreach ($card->out_of_scope as $item)
                    <li class="flex gap-2 text-sm text-gray-500">
                        <span class="text-red-400 shrink-0 mt-0.5">✕</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Notas Técnicas --}}
    @if ($card->technical_notes)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notas Técnicas</h3>
            <p class="text-sm text-gray-700 leading-relaxed font-mono">{{ $card->technical_notes }}</p>
        </div>
    @endif

    {{-- Labels --}}
    @if ($card->labels && count($card->labels) > 0)
        <div class="mb-5 flex flex-wrap gap-1.5">
            @foreach ($card->labels as $label)
                <span class="px-2.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full border border-gray-200">
                    {{ $label }}
                </span>
            @endforeach
        </div>
    @endif

    <x-session-usage :conversation="$card->conversation" class="mb-5" />

    {{-- Ações --}}
    @if (! $card->isApproved())
        <button wire:click="approve"
                class="w-full py-2.5 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 transition-colors">
            ✓ Aprovar Card
        </button>
    @else
        <a href="{{ route('cards.show', $card) }}"
           class="block text-center w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
            Ver Card Completo →
        </a>
    @endif

</div>
