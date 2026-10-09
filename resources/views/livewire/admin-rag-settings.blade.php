<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Parâmetros do RAG</h1>
        <p class="text-sm text-gray-500 mt-1">Estratégia de corte, tamanho do trecho, modelo de embedding, quantidade de trechos e score mínimo. A configuração vale para o sistema inteiro.</p>
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
            <div class="flex items-center gap-1.5">
                <x-input-label for="rag-chunk-strategy" value="Estratégia de corte" />
                <x-info-tip label="Informações sobre a estratégia de corte">
                    <ul class="space-y-2">
                        <li><span class="font-medium text-gray-900">Por seção do Markdown.</span> Corta nos títulos do documento. Seção maior que o tamanho é dividida por parágrafo. Seção com menos de 20% do tamanho junta com a vizinha.</li>
                        <li><span class="font-medium text-gray-900">Por parágrafo.</span> Agrupa parágrafos inteiros até o tamanho.</li>
                        <li><span class="font-medium text-gray-900">Por tamanho.</span> Corta a cada quantidade de caracteres, sem partir acento ou cedilha.</li>
                    </ul>
                    <p class="border-t border-gray-100 pt-3 text-gray-500">A troca vale na próxima indexação.</p>
                </x-info-tip>
            </div>
            <select
                wire:model.live="chunkStrategy"
                id="rag-chunk-strategy"
                class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            >
                @foreach ($strategies as $value => $label)
                    <option value="{{ $value }}" @selected($chunkStrategy === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('chunkStrategy')" />
        </div>

        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="rag-chunk-size" :value="$chunkSizeLabel" />
                <x-info-tip label="Informações sobre o tamanho do trecho">
                    <p>Em <span class="font-medium text-gray-900">Por tamanho</span>, é o ponto exato do corte. Em <span class="font-medium text-gray-900">Por seção</span> e <span class="font-medium text-gray-900">Por parágrafo</span>, é o máximo de cada trecho.</p>
                    <p class="border-t border-gray-100 pt-3 text-gray-500">Vale na próxima indexação. Documentos já indexados precisam ser sincronizados de novo.</p>
                </x-info-tip>
            </div>
            <x-text-input
                wire:model.live="chunkSize"
                id="rag-chunk-size"
                type="number"
                class="mt-2 block w-full"
            />
            <x-input-error class="mt-2" :messages="$errors->get('chunkSize')" />
        </div>

        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="rag-embedding-model" value="Modelo de embedding" />
                <x-info-tip label="Informações sobre o modelo de embedding">
                    <p>Modelo que gera o vetor usado na busca por similaridade.</p>
                    <p class="border-t border-gray-100 pt-3 text-gray-500">A troca vale na próxima indexação.</p>
                </x-info-tip>
            </div>
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
            <div class="flex items-center gap-1.5">
                <x-input-label for="rag-top-k" value="Top-K" />
                <x-info-tip label="Informações sobre o top-K">
                    <p>Quantos trechos mais parecidos com a pergunta entram na resposta e na busca da tela de documentos.</p>
                    <p class="border-t border-gray-100 pt-3 text-gray-500">Vale na próxima busca, sem reindexar.</p>
                </x-info-tip>
            </div>
            <x-text-input
                wire:model.live="topK"
                id="rag-top-k"
                type="number"
                class="mt-2 block w-full"
            />
            <x-input-error class="mt-2" :messages="$errors->get('topK')" />
        </div>

        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="rag-min-score" value="Score mínimo" />
                <x-info-tip label="Informações sobre o score mínimo">
                    <p>Número de 0 a 1. Trecho com similaridade menor não é usado. 0 não filtra. Sugerido: 0,30 para o text-embedding-3-small.</p>
                    <p class="border-t border-gray-100 pt-3 text-gray-500">Vale na próxima busca. Se nenhum trecho passar, o Explore responde sem os documentos.</p>
                </x-info-tip>
            </div>
            <x-text-input
                wire:model.live="minScore"
                id="rag-min-score"
                type="number"
                step="0.01"
                min="0"
                max="1"
                class="mt-2 block w-full"
            />
            <x-input-error class="mt-2" :messages="$errors->get('minScore')" />
        </div>

        <div>
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</div>
