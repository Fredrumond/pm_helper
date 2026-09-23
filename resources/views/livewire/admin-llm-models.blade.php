<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Modelos LLM</h1>
            <p class="text-sm text-gray-500 mt-1">Catálogo de modelos por provedor. Cadastre, ative, inative e ajuste o preço de modelos pagos. Identificador, nome, tier e provedor ficam fixos após o cadastro.</p>
        </div>
        @unless ($showForm || $editingPriceId)
            <button type="button"
                    wire:click="startCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Novo modelo
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
            <h2 class="text-lg font-medium text-gray-900">Novo modelo</h2>
            <p class="mt-1 text-sm text-gray-500">
                Depois de salvo, só é possível ativar/inativar e, se for pago, ajustar o preço.
            </p>

            <form wire:submit.prevent="save" class="mt-6 space-y-5">
                <div>
                    <x-input-label for="llm-model-id" value="Identificador (model_id)" />
                    <x-text-input
                        wire:model="model_id"
                        id="llm-model-id"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        placeholder="openai/gpt-4o-mini"
                        required
                        autofocus
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('model_id')" />
                </div>

                <div>
                    <x-input-label for="llm-model-name" value="Nome" />
                    <x-text-input
                        wire:model="name"
                        id="llm-model-name"
                        class="mt-1 block w-full"
                        type="text"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="llm-model-provider" value="Provedor" />
                        <select
                            wire:model.live="provider"
                            id="llm-model-provider"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            @foreach ($providers as $providerOption)
                                <option value="{{ $providerOption }}">{{ $providerLabels[$providerOption] ?? $providerOption }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('provider')" />
                    </div>

                    <div>
                        <x-input-label for="llm-model-tier" value="Tier" />
                        <select
                            wire:model.live="tier"
                            id="llm-model-tier"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            @foreach ($tiers as $tierOption)
                                <option value="{{ $tierOption }}">{{ $tierOption }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('tier')" />
                    </div>
                </div>

                @if ($tier === \App\Models\LlmModel::TIER_PAID)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5" data-price-fields>
                        <div>
                            <x-input-label for="llm-price-input" value="Preço input (USD / 1M)" />
                            <x-text-input
                                wire:model="price_input"
                                id="llm-price-input"
                                class="mt-1 block w-full font-mono"
                                type="text"
                                inputmode="decimal"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('price_input')" />
                        </div>
                        <div>
                            <x-input-label for="llm-price-cached" value="Preço cached (opcional)" />
                            <x-text-input
                                wire:model="price_cached"
                                id="llm-price-cached"
                                class="mt-1 block w-full font-mono"
                                type="text"
                                inputmode="decimal"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('price_cached')" />
                        </div>
                        <div>
                            <x-input-label for="llm-price-output" value="Preço output (USD / 1M)" />
                            <x-text-input
                                wire:model="price_output"
                                id="llm-price-output"
                                class="mt-1 block w-full font-mono"
                                type="text"
                                inputmode="decimal"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('price_output')" />
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <x-primary-button>Cadastrar</x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelForm">
                        Cancelar
                    </x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($editingPriceId)
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-6">
            <h2 class="text-lg font-medium text-gray-900">Ajustar preço</h2>
            <p class="mt-1 text-sm text-gray-500">Valores em USD por 1M de tokens.</p>

            <form wire:submit.prevent="savePrice" class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-5" data-price-edit-fields>
                <div>
                    <x-input-label for="llm-edit-price-input" value="Preço input" />
                    <x-text-input
                        wire:model="price_input"
                        id="llm-edit-price-input"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        inputmode="decimal"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('price_input')" />
                </div>
                <div>
                    <x-input-label for="llm-edit-price-cached" value="Preço cached (opcional)" />
                    <x-text-input
                        wire:model="price_cached"
                        id="llm-edit-price-cached"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        inputmode="decimal"
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('price_cached')" />
                </div>
                <div>
                    <x-input-label for="llm-edit-price-output" value="Preço output" />
                    <x-text-input
                        wire:model="price_output"
                        id="llm-edit-price-output"
                        class="mt-1 block w-full font-mono"
                        type="text"
                        inputmode="decimal"
                        required
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('price_output')" />
                </div>

                <div class="sm:col-span-3 flex items-center gap-3">
                    <x-primary-button>Salvar preço</x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelEditPrice">
                        Cancelar
                    </x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($grouped->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <p class="text-gray-500 text-sm">Nenhum modelo no catálogo.</p>
            <p class="text-gray-400 text-xs mt-1">Cadastre o primeiro modelo para o produto oferecer nas telas de chat e prompts.</p>
        </div>
    @else
        <div class="space-y-8">
            @foreach ($grouped as $providerKey => $models)
                <section wire:key="provider-{{ $providerKey }}">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                        {{ $providerLabels[$providerKey] ?? $providerKey }}
                    </h2>
                    <div class="space-y-3">
                        @foreach ($models as $model)
                            <div wire:key="llm-model-{{ $model->id }}" class="bg-white border border-gray-200 rounded-xl px-5 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-medium text-gray-900 truncate">{{ $model->name }}</p>
                                            @if ($model->active)
                                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Ativo</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inativo</span>
                                            @endif
                                            <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $model->tier }}</span>
                                        </div>
                                        <p class="text-xs font-mono text-gray-500 mt-1 truncate">{{ $model->model_id }}</p>
                                        @if ($model->isPaid())
                                            <p class="text-xs text-gray-500 mt-2 font-mono">
                                                input {{ $model->price_input ?? '—' }}
                                                · cached {{ $model->price_cached ?? '—' }}
                                                · output {{ $model->price_output ?? '—' }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($model->isPaid())
                                            <button type="button"
                                                    wire:click="startEditPrice({{ $model->id }})"
                                                    class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                                Ajustar preço
                                            </button>
                                        @endif
                                        @if ($model->active)
                                            <button type="button"
                                                    wire:click="deactivate({{ $model->id }})"
                                                    wire:confirm="Inativar o modelo {{ $model->name }}?"
                                                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800">
                                                Inativar
                                            </button>
                                        @else
                                            <button type="button"
                                                    wire:click="activate({{ $model->id }})"
                                                    class="px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800">
                                                Ativar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
