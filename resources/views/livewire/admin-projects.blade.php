<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Projetos</h1>
            <p class="text-sm text-gray-500 mt-1">Nome amigável, repositório GitHub (owner/repo) e branch de onde a pasta /docs será lida. Só administradores gerenciam esta lista.</p>
        </div>
        @unless ($showForm)
            <button type="button"
                    wire:click="startCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Novo projeto
            </button>
        @endunless
    </div>

    @if ($statusMessage)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($showForm)
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ $editingId ? 'Editar projeto' : 'Novo projeto' }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Use <span class="font-mono text-gray-700">owner/repo</span> ou cole a URL do GitHub. A branch é enviada como <span class="font-mono text-gray-700">ref</span> ao MCP.
            </p>
            @unless ($editingId)
                <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Ao cadastrar, os arquivos .md do README e da pasta /docs serão indexados. Permaneça nesta tela até a operação terminar.
                </p>
            @endunless

            <form wire:submit.prevent="save" class="mt-6 space-y-5">
                <div
                    wire:loading.flex
                    wire:target="save"
                    class="items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-3 text-sm text-indigo-900"
                    role="status"
                    aria-live="polite"
                >
                    <svg class="w-4 h-4 mt-0.5 shrink-0 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>
                        @if ($editingId)
                            Salvando o projeto…
                        @else
                            Indexação em andamento. Permaneça nesta tela; o botão fica bloqueado até concluir.
                        @endif
                    </span>
                </div>

                <div>
                    <x-input-label for="project-name" value="Nome" />
                    <x-text-input
                        wire:model="name"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        id="project-name"
                        class="mt-1 block w-full"
                        type="text"
                        required
                        autofocus
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="project-repository" value="Repositório" />
                    <x-text-input
                        wire:model="repository"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        id="project-repository"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        placeholder="octocat/hello-world"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('repository')" />
                </div>

                <div>
                    <x-input-label for="project-branch" value="Branch" />
                    <x-text-input
                        wire:model="branch"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        id="project-branch"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        placeholder="main"
                        required
                    />
                    <p class="mt-1 text-xs text-gray-500">Pasta /docs desta branch. Ex.: main, develop, feature/docs.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button wire:loading.attr="disabled" wire:target="save" class="disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Salvar alterações' : 'Cadastrar' }}
                        </span>
                        <span wire:loading wire:target="save">
                            {{ $editingId ? 'Salvando…' : 'Indexando…' }}
                        </span>
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelForm" wire:loading.attr="disabled" wire:target="save">
                        Cancelar
                    </x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($projects->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <p class="text-gray-500 text-sm">Nenhum projeto ativo.</p>
            <p class="text-gray-400 text-xs mt-1">Cadastre um repositório GitHub para os PMs usarem depois.</p>
        </div>
    @else
        <div
            wire:loading.flex
            wire:target="syncDocuments"
            class="items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-3 text-sm text-indigo-900 mb-3"
            role="status"
            aria-live="polite"
        >
            <svg class="w-4 h-4 mt-0.5 shrink-0 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span>Indexação em andamento. Permaneça nesta tela; o botão fica bloqueado até concluir.</span>
        </div>
        <div class="space-y-3">
            @foreach ($projects as $project)
                <div wire:key="project-{{ $project->id }}" @class([
                    'border rounded-xl px-5 py-4',
                    'bg-white border-gray-200' => ! $project->trashed(),
                    'bg-gray-50 border-gray-200' => $project->trashed(),
                ])>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <p @class(['font-medium truncate', 'text-gray-900' => ! $project->trashed(), 'text-gray-600' => $project->trashed()])>
                                    {{ $project->name }}
                                </p>
                                @if ($project->trashed())
                                    <span class="shrink-0 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        Desativado
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs font-mono text-gray-500 mt-1 truncate">{{ $project->repository }} @ {{ $project->branch }}</p>
                            @if ($project->documents_count === 0)
                                <span class="mt-2 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">
                                    Sem documento sincronizado
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($project->trashed())
                                <button type="button"
                                        wire:click="reactivate({{ $project->id }})"
                                        class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Reativar
                                </button>
                            @else
                                <button type="button"
                                        wire:click="syncDocuments({{ $project->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="syncDocuments, save"
                                        class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="syncDocuments({{ $project->id }})">Sincronizar</span>
                                    <span wire:loading wire:target="syncDocuments({{ $project->id }})">Sincronizando…</span>
                                </button>
                                <button type="button"
                                        wire:click="startEdit({{ $project->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="syncDocuments, save"
                                        class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Editar
                                </button>
                                <button type="button"
                                        wire:click="deactivate({{ $project->id }})"
                                        wire:confirm="Desativar o projeto {{ $project->name }}?"
                                        wire:loading.attr="disabled"
                                        wire:target="syncDocuments, save"
                                        class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Desativar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
