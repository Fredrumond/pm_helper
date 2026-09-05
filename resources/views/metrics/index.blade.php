<x-app-layout>
    <x-slot name="title">Métricas — PM Helper</x-slot>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Métricas</h1>
            <p class="text-sm text-gray-500 mt-1">Consumo de LLM e desempenho das versões de prompt nas suas conversas.</p>
        </div>

        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Chamadas</dt>
                <dd class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($summary['calls']) }}</dd>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Tokens</dt>
                <dd class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($summary['total_tokens']) }}</dd>
                <p class="text-[11px] text-gray-400 mt-1">
                    {{ number_format($summary['prompt_tokens']) }} prompt
                    · {{ number_format($summary['completion_tokens']) }} resposta
                </p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Cache</dt>
                <dd class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($summary['cached_tokens']) }}</dd>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <dt class="text-[10px] uppercase tracking-wide text-gray-400">Custo</dt>
                <dd class="text-xl font-semibold text-gray-900 mt-1">{{ $summary['formatted_cost'] }}</dd>
                <p class="text-[11px] text-gray-400 mt-1">
                    {{ number_format($summary['completed']) }} de {{ number_format($summary['conversations']) }} conversas concluídas
                </p>
            </div>
        </dl>

        <section class="bg-white border border-gray-200 rounded-xl mb-8 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Versões de prompt</h2>
                    <p class="text-xs text-gray-400 mt-1">
                        Comparação de conclusão, tokens e custo por versão do prompt da conversa.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Atual</p>
                    <p class="text-sm font-semibold text-indigo-600">discovery@{{ $currentPromptVersion }}</p>
                    @if (count($promptVersions) > 0)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ implode(', ', $promptVersions) }}</p>
                    @endif
                </div>
            </div>

            @if ($promptComparison->isEmpty())
                <p class="px-5 py-10 text-sm text-gray-400 text-center">
                    Nenhuma conversa com versão de prompt registrada ainda.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="text-left font-medium px-5 py-2.5">Versão</th>
                                <th class="text-right font-medium px-5 py-2.5">Conversas</th>
                                <th class="text-right font-medium px-5 py-2.5">Concluídas</th>
                                <th class="text-right font-medium px-5 py-2.5">Taxa</th>
                                <th class="text-right font-medium px-5 py-2.5">Chamadas</th>
                                <th class="text-right font-medium px-5 py-2.5">Tokens médios</th>
                                <th class="text-right font-medium px-5 py-2.5">Custo médio</th>
                                <th class="text-right font-medium px-5 py-2.5">Msgs médias</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($promptComparison as $row)
                                <tr @class(['bg-indigo-50/40' => $row['version'] === $currentPromptVersion])>
                                    <td class="px-5 py-3 font-medium text-gray-900">
                                        {{ $row['version'] }}
                                        @if ($row['version'] === $currentPromptVersion)
                                            <span class="ml-1 text-[10px] font-medium text-indigo-600">atual</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['conversations']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['completed']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['completion_rate'] * 100, 1) }}%</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['calls']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['avg_tokens'], 1) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ \App\Models\LlmUsage::formatCost($row['avg_cost']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['avg_messages'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="bg-white border border-gray-200 rounded-xl mb-8 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Consumo por modelo</h2>
            </div>

            @if ($byModel->isEmpty())
                <p class="px-5 py-10 text-sm text-gray-400 text-center">Nenhuma chamada registrada ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="text-left font-medium px-5 py-2.5">Modelo</th>
                                <th class="text-right font-medium px-5 py-2.5">Chamadas</th>
                                <th class="text-right font-medium px-5 py-2.5">Tokens</th>
                                <th class="text-right font-medium px-5 py-2.5">Custo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($byModel as $row)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900">{{ $row['model'] }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['calls']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row['total_tokens']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-700">{{ $row['formatted_cost'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Chamadas recentes</h2>
            </div>

            @if ($recent->isEmpty())
                <p class="px-5 py-10 text-sm text-gray-400 text-center">O consumo de cada sessão aparece aqui após a primeira resposta da LLM.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($recent as $usage)
                        <li class="px-5 py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $usage->model }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">
                                    @if ($usage->conversation)
                                        <a href="{{ route('conversations.show', $usage->conversation) }}" class="hover:text-indigo-600">
                                            {{ $usage->conversation->title }}
                                        </a>
                                        ·
                                    @endif
                                    @if ($usage->prompt_version)
                                        prompt {{ $usage->prompt_version }}
                                        ·
                                    @endif
                                    {{ $usage->provider ?? '—' }}
                                    · {{ number_format($usage->prompt_tokens) }} in
                                    · {{ number_format($usage->completion_tokens) }} out
                                    @if ($usage->cached_tokens > 0)
                                        · {{ number_format($usage->cached_tokens) }} cache
                                    @endif
                                    · {{ $usage->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-semibold text-gray-700">{{ number_format($usage->total_tokens) }} tok</p>
                                <p class="text-[11px] text-gray-400">{{ $usage->formattedCost() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-app-layout>
