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

    @if ($showForm)
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ $editingId ? 'Editar projeto' : 'Novo projeto' }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Use <span class="font-mono text-gray-700">owner/repo</span> ou cole a URL do GitHub. A branch é enviada como <span class="font-mono text-gray-700">ref</span> ao MCP.
            </p>

            <form wire:submit.prevent="save" class="mt-6 space-y-5">
                <div>
                    <x-input-label for="project-name" value="Nome" />
                    <x-text-input
                        wire:model="name"
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
                    <x-primary-button>
                        {{ $editingId ? 'Salvar alterações' : 'Cadastrar' }}
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelForm">
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
        <div class="space-y-3">
            @foreach ($projects as $project)
                <div wire:key="project-{{ $project->id }}" class="bg-white border border-gray-200 rounded-xl px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $project->name }}</p>
                            <p class="text-xs font-mono text-gray-500 mt-1 truncate">{{ $project->repository }} @ {{ $project->branch }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    wire:click="startEdit({{ $project->id }})"
                                    class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Editar
                            </button>
                            <button type="button"
                                    wire:click="deactivate({{ $project->id }})"
                                    wire:confirm="Desativar o projeto {{ $project->name }}?"
                                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800">
                                Desativar
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
