<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Parâmetros do RAG</h1>
        <p class="text-sm text-gray-500 mt-1">Tamanho do chunk, modelo de embedding e quantidade de trechos recuperados. A configuração vale para o sistema inteiro.</p>
    </div>

    @if ($statusMessage)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($reindexLabel)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
            {{ $reindexLabel }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white border border-gray-200 rounded-xl px-5 py-5 space-y-6">
        <div>
            <x-input-label for="rag-chunk-size" value="Tamanho do chunk" />
            <x-text-input
                wire:model.live="chunkSize"
                id="rag-chunk-size"
                type="number"
                class="mt-2 block w-full"
            />
            <x-input-error class="mt-2" :messages="$errors->get('chunkSize')" />
        </div>

        <div>
            <x-input-label for="rag-embedding-model" value="Modelo de embedding" />
            <select
                wire:model.live="embeddingModel"
                id="rag-embedding-model"
                class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            >
                @foreach ($catalog as $model)
                    <option value="{{ $model['id'] }}" @selected($embeddingModel === $model['id'])>
                        {{ $model['name'].($model['active'] ? '' : ' (inativo)') }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('embeddingModel')" />
        </div>

        <div>
            <x-input-label for="rag-top-k" value="Top-K" />
            <x-text-input
                wire:model.live="topK"
                id="rag-top-k"
                type="number"
                class="mt-2 block w-full"
            />
            <x-input-error class="mt-2" :messages="$errors->get('topK')" />
        </div>

        <div>
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</div>
