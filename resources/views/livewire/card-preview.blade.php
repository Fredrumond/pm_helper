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
                    {{ match($card->priority) {
                        'low'      => 'bg-gray-100 text-gray-500',
                        'medium'   => 'bg-yellow-100 text-yellow-700',
                        'high'     => 'bg-orange-100 text-orange-700',
                        'critical' => 'bg-red-100 text-red-700',
                        default    => 'bg-gray-100 text-gray-500',
                    } }}">
                    ↑ {{ $card->priorityLabel() }}
                </span>
                @if ($card->conversation?->project?->name)
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">{{ $card->conversation->project->name }}</span>
                @endif
            </div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">{{ $card->title }}</h2>
        </div>
        <span class="shrink-0 px-2 py-0.5 text-xs rounded-full
            {{ $card->isApproved() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
            {{ $card->isApproved() ? '✓ Aprovado' : 'Rascunho' }}
        </span>
    </div>

    @if ($card->objetivo)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Objetivo</h3>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $card->objetivo }}</p>
        </div>
    @endif

    @if ($card->como_funciona_hoje)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Como funciona hoje</h3>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $card->como_funciona_hoje }}</p>
        </div>
    @endif

    @if ($card->regras && count($card->regras) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Regras</h3>
            <ul class="space-y-2">
                @foreach ($card->regras as $regra)
                    <li class="flex gap-2 text-sm text-gray-700">
                        <span class="text-indigo-500 shrink-0 mt-0.5">•</span>
                        <span>{{ $regra }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card->onde && count($card->onde) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Onde</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($card->onde as $canal)
                    <span class="px-2.5 py-0.5 text-xs bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100">
                        {{ $canal }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($card->aceite && count($card->aceite) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Aceite</h3>
            <ul class="space-y-2">
                @foreach ($card->aceite as $criterio)
                    <li class="flex gap-2 text-sm text-gray-700">
                        <span class="text-green-500 shrink-0 mt-0.5">✓</span>
                        <span>{{ $criterio }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card->o_que_nao_fazer && count($card->o_que_nao_fazer) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">O que não fazer</h3>
            <ul class="space-y-2">
                @foreach ($card->o_que_nao_fazer as $item)
                    <li class="flex gap-2 text-sm text-gray-500">
                        <span class="text-red-400 shrink-0 mt-0.5">✕</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($card->stakeholders && count($card->stakeholders) > 0)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Stakeholders</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($card->stakeholders as $pessoa)
                    <span class="px-2.5 py-0.5 text-xs bg-gray-100 text-gray-700 rounded-full border border-gray-200">
                        {{ $pessoa }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($card->como_validar)
        <div class="mb-4 bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Como validar</h3>
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $card->como_validar }}</p>
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
