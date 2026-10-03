<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Documentos</h1>
            <p class="text-sm text-gray-500 mt-1">Envie um .md e veja os chunks mais próximos da sua pergunta por similaridade.</p>
        </div>
        @unless ($showForm)
            <button type="button"
                    wire:click="startCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Enviar documento
            </button>
        @endunless
    </div>

    @if ($statusMessage)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-6">
        <h2 class="text-lg font-medium text-gray-900">Perguntar aos documentos</h2>
        <p class="mt-1 text-sm text-gray-500">
            Pergunta → embedding → top 3 chunks mais próximos por similaridade de cosseno.
        </p>

        <form wire:submit.prevent="search" class="mt-5 space-y-4">
            <div>
                <x-input-label for="document-question" value="Pergunta" />
                <x-text-input
                    wire:model="question"
                    id="document-question"
                    class="mt-1 block w-full"
                    type="text"
                    placeholder="Ex.: quantos quilos de Lumina?"
                />
                <x-input-error class="mt-2" :messages="$errors->get('question')" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>
                    Buscar
                </x-primary-button>
                <span wire:loading wire:target="search" class="text-xs text-gray-500">
                    Buscando chunks…
                </span>
            </div>
        </form>

        @if ($searched)
            @if (count($matches) === 0)
                <p class="mt-5 text-sm text-gray-500">Nenhum chunk com embedding para comparar.</p>
            @else
                <ol class="mt-5 space-y-3">
                    @foreach ($matches as $match)
                        <li class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ number_format($match['score'], 2) }}
                                    <span class="font-normal text-gray-500">
                                        · {{ $match['document'] }}
                                        · chunk {{ $match['chunk_index'] }}
                                    </span>
                                </p>
                            </div>
                            <pre class="mt-2 text-xs text-gray-800 whitespace-pre-wrap">{{ $match['content'] }}</pre>
                        </li>
                    @endforeach
                </ol>
            @endif
        @endif
    </div>

    @if ($showForm)
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ $editingId ? 'Editar documento' : 'Novo documento' }}
            </h2>

            <form wire:submit.prevent="save" class="mt-6 space-y-5">
                @if ($editingId === null)
                    <div>
                        <x-input-label for="document-file" value="Arquivo .md" />
                        <input
                            wire:model="file"
                            id="document-file"
                            type="file"
                            accept=".md,text/markdown,text/plain"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <p class="mt-1 text-xs text-gray-500">Máximo 5 MB. O nome do arquivo vira o título do registro.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('file')" />
                        <div wire:loading wire:target="file" class="mt-2 text-xs text-gray-500">Carregando arquivo…</div>
                    </div>
                @else
                    <div>
                        <x-input-label for="document-name" value="Nome" />
                        <x-text-input
                            wire:model="name"
                            id="document-name"
                            class="mt-1 block w-full font-mono"
                            type="text"
                            required
                            autofocus
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="document-content" value="Conteúdo" />
                        <textarea
                            wire:model="content"
                            id="document-content"
                            rows="12"
                            class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            required
                        ></textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('content')" />
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <x-primary-button>
                        {{ $editingId ? 'Salvar alterações' : 'Enviar' }}
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelForm">
                        Cancelar
                    </x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($documents->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500 text-sm">Nenhum documento cadastrado.</p>
            <p class="text-gray-400 text-xs mt-1">Envie um arquivo .md para começar.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($documents as $document)
                <div wire:key="document-{{ $document->id }}" class="bg-white border border-gray-200 rounded-xl px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900 truncate font-mono">{{ $document->name }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ number_format(strlen($document->content)) }} caracteres
                                · {{ $document->chunks_count }} {{ $document->chunks_count === 1 ? 'chunk' : 'chunks' }}
                                · {{ $document->created_at->format('d/m/Y H:i') }}
                            </p>
                            <details class="mt-3 group">
                                <summary class="text-sm text-indigo-600 cursor-pointer hover:text-indigo-800 select-none">
                                    Ver conteúdo
                                </summary>
                                <pre class="mt-2 p-3 bg-gray-50 rounded-lg text-xs text-gray-800 overflow-x-auto whitespace-pre-wrap border border-gray-100 max-h-64 overflow-y-auto">{{ $document->content }}</pre>
                            </details>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    wire:click="startEdit({{ $document->id }})"
                                    class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Editar
                            </button>
                            <button type="button"
                                    wire:click="destroy({{ $document->id }})"
                                    wire:confirm="Excluir o documento {{ $document->name }}?"
                                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800">
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
