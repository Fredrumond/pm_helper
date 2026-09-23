<?php

namespace App\Services;

use App\Exceptions\LlmModelInUseException;
use App\Exceptions\LlmModelValidationException;
use App\Models\LlmModel;
use Illuminate\Database\Eloquent\Collection;

class LlmModelCatalog
{
    public function __construct(private readonly PromptModelConfig $promptModels)
    {
    }

    /**
     * @return Collection<int, LlmModel>
     */
    public function list(): Collection
    {
        return LlmModel::query()->orderBy('provider')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, LlmModel>
     */
    public function active(): Collection
    {
        return LlmModel::query()
            ->where('active', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();
    }

    public function findActive(string $modelId): ?LlmModel
    {
        return LlmModel::query()
            ->where('model_id', $modelId)
            ->where('active', true)
            ->first();
    }

    /**
     * @param  array{model_id: string, name: string, tier: string, provider: string, price_input?: numeric-string|float|int|null, price_cached?: numeric-string|float|int|null, price_output?: numeric-string|float|int|null}  $data
     */
    public function register(array $data): LlmModel
    {
        $modelId = trim((string) ($data['model_id'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $tier = (string) ($data['tier'] ?? '');
        $provider = (string) ($data['provider'] ?? '');

        if ($modelId === '') {
            throw new LlmModelValidationException('O identificador do modelo (model_id) é obrigatório.');
        }

        if ($name === '') {
            throw new LlmModelValidationException('O nome do modelo é obrigatório.');
        }

        if (! in_array($provider, LlmModel::PROVIDERS, true)) {
            throw new LlmModelValidationException(
                "Provedor inválido [{$provider}]. Use um dos: ".implode(', ', LlmModel::PROVIDERS).'.'
            );
        }

        if (! in_array($tier, LlmModel::TIERS, true)) {
            throw new LlmModelValidationException(
                "Tier inválido [{$tier}]. Use um dos: ".implode(', ', LlmModel::TIERS).'.'
            );
        }

        if (LlmModel::query()->where('model_id', $modelId)->exists()) {
            throw new LlmModelValidationException("Já existe um modelo cadastrado com o id [{$modelId}].");
        }

        $prices = ['price_input' => null, 'price_cached' => null, 'price_output' => null];

        if ($tier === LlmModel::TIER_PAID) {
            $prices = $this->validatedPrices($data);
        }

        return LlmModel::query()->create([
            'model_id' => $modelId,
            'name' => $name,
            'tier' => $tier,
            'provider' => $provider,
            'active' => true,
            ...$prices,
        ]);
    }

    public function activate(int $id): LlmModel
    {
        /** @var LlmModel $model */
        $model = LlmModel::query()->findOrFail($id);
        $model->active = true;
        $model->save();

        return $model;
    }

    public function deactivate(int $id): LlmModel
    {
        /** @var LlmModel $model */
        $model = LlmModel::query()->findOrFail($id);

        $usedBy = $this->promptsUsing($model->model_id);

        if ($usedBy !== []) {
            throw new LlmModelInUseException($model->model_id, $usedBy);
        }

        $model->active = false;
        $model->save();

        return $model;
    }

    /**
     * @param  array{price_input?: numeric-string|float|int|null, price_cached?: numeric-string|float|int|null, price_output?: numeric-string|float|int|null}  $prices
     */
    public function updatePrice(int $id, array $prices): LlmModel
    {
        /** @var LlmModel $model */
        $model = LlmModel::query()->findOrFail($id);

        if (! $model->isPaid()) {
            throw new LlmModelValidationException(
                "O modelo [{$model->model_id}] não é pago (tier={$model->tier}); preço não se aplica."
            );
        }

        $validated = $this->validatedPrices($prices);

        $model->fill($validated);
        $model->save();

        return $model;
    }

    /**
     * @return list<string>
     */
    private function promptsUsing(string $modelId): array
    {
        $prompts = [];

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            if ($this->promptModels->active($prompt) === $modelId) {
                $prompts[] = $prompt;
            }
        }

        return $prompts;
    }

    /**
     * @param  array{price_input?: numeric-string|float|int|null, price_cached?: numeric-string|float|int|null, price_output?: numeric-string|float|int|null}  $data
     * @return array{price_input: float, price_cached: float|null, price_output: float}
     */
    private function validatedPrices(array $data): array
    {
        $input = $data['price_input'] ?? null;
        $cached = $data['price_cached'] ?? null;
        $output = $data['price_output'] ?? null;

        if ($input === null || $output === null || ! is_numeric($input) || ! is_numeric($output)) {
            throw new LlmModelValidationException('Modelo pago exige preço de entrada (price_input) e saída (price_output).');
        }

        if ($cached !== null && ! is_numeric($cached)) {
            throw new LlmModelValidationException('O preço de cache (price_cached), quando informado, deve ser numérico.');
        }

        return [
            'price_input' => (float) $input,
            'price_cached' => $cached === null ? null : (float) $cached,
            'price_output' => (float) $output,
        ];
    }
}
