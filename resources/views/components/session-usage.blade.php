@props(['conversation'])

@php
    $conversation->loadMissing('llmUsages');
    $usages = $conversation->llmUsages;
    $summary = $conversation->usageSummary();
@endphp

@if ($usages->isNotEmpty())
    <section {{ $attributes->merge(['class' => 'rounded-xl border border-indigo-100 bg-indigo-50/60 p-4']) }}>
        <h3 class="text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-3">Consumo da sessão</h3>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            <div class="bg-white/80 rounded-lg px-3 py-2 border border-indigo-50">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Chamadas</dt>
                <dd class="text-sm font-semibold text-gray-800">{{ $summary['calls'] }}</dd>
            </div>
            <div class="bg-white/80 rounded-lg px-3 py-2 border border-indigo-50">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Tokens</dt>
                <dd class="text-sm font-semibold text-gray-800">{{ number_format($summary['total_tokens']) }}</dd>
            </div>
            <div class="bg-white/80 rounded-lg px-3 py-2 border border-indigo-50">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Cache</dt>
                <dd class="text-sm font-semibold text-gray-800">{{ number_format($summary['cached_tokens']) }}</dd>
            </div>
            <div class="bg-white/80 rounded-lg px-3 py-2 border border-indigo-50">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Custo</dt>
                <dd class="text-sm font-semibold text-gray-800">{{ $conversation->formattedUsageCost() }}</dd>
            </div>
        </dl>

        <p class="text-[11px] text-gray-400 mb-2">
            {{ number_format($summary['prompt_tokens']) }} prompt
            · {{ number_format($summary['completion_tokens']) }} resposta
        </p>

        <ul class="space-y-2">
            @foreach ($usages as $index => $usage)
                <li class="bg-white rounded-lg border border-gray-100 px-3 py-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-gray-800 truncate">
                                #{{ $index + 1 }} {{ $usage->model }}
                            </p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                {{ $usage->provider ?? '—' }}
                                · {{ number_format($usage->prompt_tokens) }} in
                                · {{ number_format($usage->completion_tokens) }} out
                                @if ($usage->cached_tokens > 0)
                                    · {{ number_format($usage->cached_tokens) }} cache
                                @endif
                                @if ($usage->finish_reason)
                                    · {{ $usage->finish_reason }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-semibold text-gray-700">{{ number_format($usage->total_tokens) }} tok</p>
                            <p class="text-[11px] text-gray-400">{{ $usage->formattedCost() }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
