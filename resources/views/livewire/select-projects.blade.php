<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Projetos</h1>
        <p class="text-sm text-gray-500 mt-1">Escolha o projeto com o qual você vai trabalhar. A seleção vale só para esta sessão.</p>
    </div>

    @if ($selectedProjectId)
        <div class="flex items-center justify-between gap-4 mb-6 bg-indigo-50 border border-indigo-100 rounded-xl px-5 py-3">
            <p class="text-sm text-indigo-900">
                Projeto selecionado:
                <span class="font-medium">{{ $projects->firstWhere('id', $selectedProjectId)?->name }}</span>
            </p>
            <button type="button"
                    wire:click="clear"
                    class="shrink-0 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:text-indigo-900">
                Limpar seleção
            </button>
        </div>
    @endif

    @if ($projects->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <p class="text-gray-500 text-sm">Nenhum projeto ativo.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($projects as $project)
                @php
                    $isSelected = $selectedProjectId !== null && (int) $selectedProjectId === $project->id;
                @endphp
                <div wire:key="project-{{ $project->id }}"
                     @class([
                         'bg-white border rounded-xl px-5 py-4',
                         'border-indigo-500 ring-1 ring-indigo-500' => $isSelected,
                         'border-gray-200' => ! $isSelected,
                     ])>
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex items-center gap-3">
                            <p class="font-medium text-gray-900 truncate">{{ $project->name }}</p>
                            @if ($isSelected)
                                <span class="shrink-0 inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                    Selecionado
                                </span>
                            @endif
                        </div>
                        @unless ($isSelected)
                            <button type="button"
                                    wire:click="select({{ $project->id }})"
                                    class="shrink-0 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Selecionar
                            </button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
