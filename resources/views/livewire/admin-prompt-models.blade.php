<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Modelos dos prompts</h1>
        <p class="text-sm text-gray-500 mt-1">Escolha, para cada prompt, um modelo da lista que o produto já oferece. A configuração vale para o sistema inteiro.</p>
    </div>

    @if ($statusMessage)
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
            {{ $statusMessage }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white border border-gray-200 rounded-xl px-5 py-5 space-y-6">
        @foreach ($prompts as $prompt)
            <div wire:key="prompt-model-{{ $prompt }}">
                <x-input-label :for="'prompt-model-'.$prompt" :value="$labels[$prompt]" />
                <p class="mt-1 text-xs font-mono text-gray-500">{{ $prompt }}</p>
                <select
                    wire:model="models.{{ $prompt }}"
                    id="prompt-model-{{ $prompt }}"
                    class="mt-2 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                >
                    @foreach ($catalog as $model)
                        <option value="{{ $model['id'] }}" @selected($models[$prompt] === $model['id'])>
                            {{ $model['name'] }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('models.'.$prompt)" />
            </div>
        @endforeach

        <div>
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</div>
